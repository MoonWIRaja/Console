<?php

namespace Pterodactyl\Services\Backups;

use Illuminate\Http\Response;
use Pterodactyl\Models\Backup;
use Pterodactyl\Models\Server;
use GuzzleHttp\Exception\ClientException;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;

class RestoreContainerLocalBackupService
{
    public function __construct(private DaemonFileRepository $fileRepository)
    {
    }

    /**
     * Restores a server from a backup archive stored in its own file system.
     *
     * @throws \Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException
     */
    public function handle(Server $server, Backup $backup, bool $truncate): void
    {
        $directory = $this->localContainerDirectory();
        $filename = sprintf('backup-%s.tar.gz', $backup->uuid);
        $sourcePath = rtrim($directory, '/') . '/' . $filename;
        $tempFilename = sprintf('.restore-%s.tar.gz', $backup->uuid);
        $tempPath = '/' . $tempFilename;

        $this->fileRepository
            ->setServer($server)
            ->renameFiles('/', [
                [
                    'from' => $sourcePath,
                    'to' => $tempPath,
                ],
            ]);

        try {
            if ($truncate) {
                $this->truncateForLocalRestore($server, $tempFilename);
            }

            $this->fileRepository
                ->setServer($server)
                ->decompressFile('/', $tempFilename);
        } finally {
            $this->ensureDirectory($server, $directory);

            $this->fileRepository
                ->setServer($server)
                ->renameFiles('/', [
                    [
                        'from' => $tempPath,
                        'to' => $sourcePath,
                    ],
                ]);
        }
    }

    private function truncateForLocalRestore(Server $server, string $tempArchiveName): void
    {
        $localRootSegment = trim(explode('/', trim($this->localContainerDirectory(), '/'))[0] ?? '');
        $protected = array_filter([
            $tempArchiveName,
            '.recycle_bin',
            $localRootSegment,
        ], fn (string $value) => $value !== '');

        $entries = $this->fileRepository->setServer($server)->getDirectory('/');
        $delete = [];

        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $name = trim((string) ($entry['name'] ?? ''));
            if ($name === '' || $name === '.' || $name === '..') {
                continue;
            }

            if (in_array($name, $protected, true)) {
                continue;
            }

            $delete[] = $name;
        }

        if (empty($delete)) {
            return;
        }

        $this->fileRepository
            ->setServer($server)
            ->deleteFiles('/', array_values($delete));
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
}

