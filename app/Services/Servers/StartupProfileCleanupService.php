<?php

namespace Pterodactyl\Services\Servers;

use Throwable;
use Pterodactyl\Models\Server;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Services\Backups\DeleteBackupService;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;

class StartupProfileCleanupService
{
    public function __construct(
        private DeleteBackupService $deleteBackupService,
        private DaemonFileRepository $fileRepository,
    ) {
    }

    /**
     * Remove all existing server data before a destructive Game Type switch.
     *
     * @return array{deleted_backups:int, deleted_container_entries:int}
     *
     * @throws DisplayException
     */
    public function handleNestChange(Server $server): array
    {
        return [
            'deleted_backups' => $this->deleteBackups($server),
            'deleted_container_entries' => $this->deleteContainerEntries($server),
        ];
    }

    /**
     * @throws DisplayException
     */
    private function deleteBackups(Server $server): int
    {
        $deleted = 0;
        $backups = $server->backups()->get();

        foreach ($backups as $backup) {
            try {
                $this->deleteBackupService->handle($backup);
                ++$deleted;
            } catch (Throwable $exception) {
                throw new DisplayException(
                    'Unable to remove existing backups before changing Game Type. Please verify backup storage access and try again.',
                    $exception
                );
            }
        }

        return $deleted;
    }

    /**
     * @throws DisplayException
     */
    private function deleteContainerEntries(Server $server): int
    {
        try {
            $entries = $this->fileRepository
                ->setServer($server)
                ->getDirectory('/');
        } catch (Throwable $exception) {
            throw new DisplayException(
                'Unable to inspect the server container before changing Game Type. Please ensure the node is reachable and try again.',
                $exception
            );
        }

        $delete = [];

        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $name = trim((string) ($entry['name'] ?? ''));
            if ($name === '' || $name === '.' || $name === '..') {
                continue;
            }

            $delete[] = $name;
        }

        if (empty($delete)) {
            return 0;
        }

        try {
            $this->fileRepository
                ->setServer($server)
                ->deleteFiles('/', array_values($delete));
        } catch (Throwable $exception) {
            throw new DisplayException(
                'Unable to clear the server files before changing Game Type. Please ensure the node is reachable and try again.',
                $exception
            );
        }

        return count($delete);
    }
}
