<?php

namespace Pterodactyl\Services\Backups;

use Throwable;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Models\Backup;
use Pterodactyl\Models\Server;
use GuzzleHttp\Exception\ClientException;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;

class MirrorBackupToServerService
{
    public function __construct(
        private DaemonFileRepository $fileRepository,
        private DownloadLinkService $downloadLinkService,
    ) {
    }

    public function enabled(): bool
    {
        return (bool) config('backups.mirror_to_server.enabled', false);
    }

    public function mirror(Backup $backup): void
    {
        if (!$this->enabled() || !$backup->is_successful) {
            return;
        }

        $backup->loadMissing(['server.user', 'server.node']);
        $server = $backup->server;
        $user = $server->user;
        if (!$server || !$user) {
            return;
        }

        $directory = $this->normalizedDirectory();
        $filename = $this->filename($backup);

        try {
            $this->ensureDirectory($server, $directory);

            $url = $this->downloadLinkService->handle($backup, $user);
            $this->fileRepository
                ->setServer($server)
                ->pull($url, $directory, [
                    'filename' => $filename,
                    'foreground' => false,
                    'use_header' => false,
                ]);
        } catch (Throwable $exception) {
            Log::warning('Failed to mirror backup into server file system.', [
                'backup_uuid' => $backup->uuid,
                'server_uuid' => $server->uuid,
                'directory' => $directory,
                'filename' => $filename,
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    public function remove(Backup $backup): void
    {
        if (!$this->enabled()) {
            return;
        }

        $backup->loadMissing('server');
        $server = $backup->server;
        if (!$server) {
            return;
        }

        $directory = $this->normalizedDirectory();
        $filename = $this->filename($backup);

        try {
            $this->fileRepository
                ->setServer($server)
                ->deleteFiles($directory, [$filename]);
        } catch (DaemonConnectionException $exception) {
            $previous = $exception->getPrevious();
            if ($previous instanceof ClientException && $previous->getResponse()->getStatusCode() === Response::HTTP_NOT_FOUND) {
                return;
            }

            Log::warning('Failed to remove mirrored backup file from server file system.', [
                'backup_uuid' => $backup->uuid,
                'server_uuid' => $server->uuid,
                'directory' => $directory,
                'filename' => $filename,
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    private function normalizedDirectory(): string
    {
        $configured = trim((string) config('backups.mirror_to_server.directory', '/backups'));
        if ($configured === '' || $configured === '.') {
            return '/';
        }

        $normalized = '/' . trim(str_replace('\\', '/', $configured), '/');
        $normalized = preg_replace('#/+#', '/', $normalized) ?? '/';

        return $normalized === '' ? '/' : $normalized;
    }

    private function filename(Backup $backup): string
    {
        return sprintf('backup-%s.tar.gz', $backup->uuid);
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
                        // Directory likely already exists.
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
