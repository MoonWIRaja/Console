<?php

namespace Pterodactyl\Services\Backups;

use Ramsey\Uuid\Uuid;
use Carbon\CarbonImmutable;
use Webmozart\Assert\Assert;
use Pterodactyl\Models\Backup;
use Pterodactyl\Models\Server;
use Illuminate\Http\Response;
use Illuminate\Database\ConnectionInterface;
use Pterodactyl\Extensions\Backups\BackupManager;
use Pterodactyl\Repositories\Eloquent\BackupRepository;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;
use GuzzleHttp\Exception\ClientException;
use Pterodactyl\Repositories\Wings\DaemonBackupRepository;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Exceptions\Service\Backup\TooManyBackupsException;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

class InitiateBackupService
{
    private array $ignoredFiles = [];

    private bool $isLocked = false;

    /**
     * InitiateBackupService constructor.
     */
    public function __construct(
        private BackupRepository $repository,
        private ConnectionInterface $connection,
        private DaemonBackupRepository $daemonBackupRepository,
        private DaemonFileRepository $fileRepository,
        private DeleteBackupService $deleteBackupService,
        private BackupManager $backupManager,
    ) {
    }

    /**
     * Set if the backup should be locked once it is created which will prevent
     * its deletion by users or automated system processes.
     */
    public function setIsLocked(bool $isLocked): self
    {
        $this->isLocked = $isLocked;

        return $this;
    }

    /**
     * Sets the files to be ignored by this backup.
     *
     * @param string[]|null $ignored
     */
    public function setIgnoredFiles(?array $ignored): self
    {
        if (is_array($ignored)) {
            foreach ($ignored as $value) {
                Assert::string($value); // @phpstan-ignore staticMethod.alreadyNarrowedType
            }
        }

        // Set the ignored files to be any values that are not empty in the array. Don't use
        // the PHP empty function here incase anything that is "empty" by default (0, false, etc.)
        // were passed as a file or folder name.
        $this->ignoredFiles = is_null($ignored) ? [] : array_filter($ignored, function ($value) {
            return strlen($value) > 0;
        });

        return $this;
    }

    /**
     * Initiates the backup process for a server on Wings.
     *
     * @throws \Throwable
     * @throws TooManyBackupsException
     * @throws TooManyRequestsHttpException
     */
    public function handle(Server $server, ?string $name = null, bool $override = false): Backup
    {
        $limit = config('backups.throttles.limit');
        $period = config('backups.throttles.period');
        if ($period > 0) {
            $previous = $this->repository->getBackupsGeneratedDuringTimespan($server->id, $period);
            if ($previous->count() >= $limit) {
                $message = sprintf('Only %d backups may be generated within a %d second span of time.', $limit, $period);

                throw new TooManyRequestsHttpException((int) CarbonImmutable::now()->diffInSeconds($previous->last()->created_at->addSeconds($period)), $message);
            }
        }

        // Check if the server has reached or exceeded its backup limit.
        // completed_at == null will cover any ongoing backups, while is_successful == true will cover any completed backups.
        $successful = $this->repository->getNonFailedBackups($server);
        if (!$server->backup_limit || $successful->count() >= $server->backup_limit) {
            // Do not allow the user to continue if this server is already at its limit and can't override.
            if (!$override || $server->backup_limit <= 0) {
                throw new TooManyBackupsException($server->backup_limit);
            }

            // Get the oldest backup the server has that is not "locked" (indicating a backup that should
            // never be automatically purged). If we find a backup we will delete it and then continue with
            // this process. If no backup is found that can be used an exception is thrown.
            $oldest = $successful->where('is_locked', false)->orderBy('created_at')->first();
            if (!$oldest) {
                throw new TooManyBackupsException($server->backup_limit);
            }

            $this->deleteBackupService->handle($oldest);
        }

        if ($this->isLocalContainerOnlyEnabled()) {
            return $this->handleLocalContainerBackup($server, $name);
        }

        return $this->connection->transaction(function () use ($server, $name) {
            /** @var Backup $backup */
            $backup = $this->repository->create([
                'server_id' => $server->id,
                'uuid' => Uuid::uuid4()->toString(),
                'name' => trim($name) ?: sprintf('Backup at %s', CarbonImmutable::now()->toDateTimeString()),
                'ignored_files' => array_values($this->ignoredFiles),
                'disk' => $this->backupManager->getDefaultAdapter(),
                'is_locked' => $this->isLocked,
            ], true, true);

            $this->daemonBackupRepository->setServer($server)
                ->setBackupAdapter($this->backupManager->getDefaultAdapter())
                ->backup($backup);

            return $backup;
        });
    }

    /**
     * Creates a backup archive directly inside the server file system.
     *
     * @throws \Throwable
     */
    private function handleLocalContainerBackup(Server $server, ?string $name = null): Backup
    {
        $uuid = Uuid::uuid4()->toString();
        $targetDirectory = $this->localContainerDirectory();
        $targetFilename = sprintf('backup-%s.tar.gz', $uuid);
        $targetPath = rtrim($targetDirectory, '/') . '/' . $targetFilename;
        $targetRootSegment = trim(explode('/', trim($targetDirectory, '/'))[0] ?? '');

        $this->ensureDirectory($server, $targetDirectory);

        $before = $this->listDirectoryNames($server, '/');
        $files = array_values(array_filter($before, function (string $entry) use ($targetRootSegment): bool {
            if ($entry === '' || $entry === '.' || $entry === '..') {
                return false;
            }

            if ($entry === '.recycle_bin') {
                return false;
            }

            if ($targetRootSegment !== '' && $entry === $targetRootSegment) {
                return false;
            }

            return true;
        }));

        if (empty($files)) {
            throw new DisplayException('No files available to back up for this server.');
        }

        $compressed = $this->fileRepository
            ->setServer($server)
            ->compressFiles('/', $files);

        $archiveName = $this->extractArchiveName($compressed);
        if ($archiveName === '') {
            $after = $this->listDirectoryNames($server, '/');
            $newEntries = array_values(array_diff($after, $before));
            $archiveName = $this->detectArchiveEntry($newEntries);
        }

        if ($archiveName === '') {
            throw new DisplayException('Unable to determine generated archive file name for local backup.');
        }

        $sourcePath = '/' . ltrim($archiveName, '/');
        if ($sourcePath !== $targetPath) {
            $this->fileRepository
                ->setServer($server)
                ->renameFiles('/', [
                    [
                        'from' => $sourcePath,
                        'to' => $targetPath,
                    ],
                ]);
        }

        $bytes = $this->readFileSize($server, $targetDirectory, $targetFilename);

        return $this->connection->transaction(function () use ($server, $uuid, $name, $bytes) {
            /** @var Backup $backup */
            $backup = $this->repository->create([
                'server_id' => $server->id,
                'uuid' => $uuid,
                'name' => trim((string) $name) ?: sprintf('Backup at %s', CarbonImmutable::now()->toDateTimeString()),
                'ignored_files' => array_values($this->ignoredFiles),
                'disk' => Backup::ADAPTER_CONTAINER_LOCAL,
                'is_locked' => $this->isLocked,
                'is_successful' => true,
                'bytes' => $bytes,
                'completed_at' => CarbonImmutable::now(),
            ], true, true);

            return $backup;
        });
    }

    private function isLocalContainerOnlyEnabled(): bool
    {
        return (bool) config('backups.local_container_only', false);
    }

    private function localContainerDirectory(): string
    {
        $configured = trim((string) config('backups.local_container_directory', '/backups'));
        if ($configured === '' || $configured === '.') {
            return '/backups';
        }

        $normalized = '/' . trim(str_replace('\\', '/', $configured), '/');
        $normalized = preg_replace('#/+#', '/', $normalized) ?? '/backups';

        return $normalized === '/' ? '/backups' : $normalized;
    }

    private function extractArchiveName(array $compressed): string
    {
        $name = trim((string) ($compressed['name'] ?? $compressed['file'] ?? ''));
        return ltrim($name, '/');
    }

    /**
     * @param array<int, string> $entries
     */
    private function detectArchiveEntry(array $entries): string
    {
        foreach ($entries as $entry) {
            $normalized = ltrim(trim($entry), '/');
            if ($normalized === '') {
                continue;
            }

            $lower = strtolower($normalized);
            if (str_ends_with($lower, '.tar.gz') || str_ends_with($lower, '.tar') || str_ends_with($lower, '.zip')) {
                return $normalized;
            }
        }

        return '';
    }

    /**
     * @return array<int, string>
     */
    private function listDirectoryNames(Server $server, string $path): array
    {
        $raw = $this->fileRepository
            ->setServer($server)
            ->getDirectory($path);

        $names = [];
        foreach ($raw as $item) {
            if (!is_array($item)) {
                continue;
            }

            $name = trim((string) ($item['name'] ?? ''));
            if ($name !== '') {
                $names[] = $name;
            }
        }

        return $names;
    }

    private function readFileSize(Server $server, string $directory, string $filename): int
    {
        $entries = $this->fileRepository->setServer($server)->getDirectory($directory);
        foreach ($entries as $item) {
            if (!is_array($item)) {
                continue;
            }

            if ((string) ($item['name'] ?? '') !== $filename) {
                continue;
            }

            return max(0, (int) ($item['size'] ?? 0));
        }

        return 0;
    }

    private function ensureDirectory(Server $server, string $directory): void
    {
        if ($directory === '/' || $directory === '') {
            return;
        }

        $segments = array_values(array_filter(explode('/', trim($directory, '/'))));
        $current = '/';

        foreach ($segments as $segment) {
            try {
                $this->fileRepository
                    ->setServer($server)
                    ->createDirectory($segment, $current);
            } catch (DaemonConnectionException $exception) {
                $previous = $exception->getPrevious();
                if ($previous instanceof ClientException) {
                    $status = $previous->getResponse()->getStatusCode();
                    if ($status === Response::HTTP_CONFLICT || $status === Response::HTTP_BAD_REQUEST) {
                        // Directory already exists.
                    } else {
                        throw $exception;
                    }
                } else {
                    throw $exception;
                }
            }

            $current = rtrim($current, '/') . '/' . $segment;
        }
    }
}
