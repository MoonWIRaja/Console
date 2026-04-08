<?php

namespace Pterodactyl\Services\RecycleBin;

use Pterodactyl\Models\Server;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;
use Carbon\Carbon;

class RecycleBinMetadataService
{
    private const METADATA_FILE = '.recycle_bin/.metadata.json';

    public function __construct(
        private DaemonFileRepository $fileRepository
    ) {
    }

    /**
     * Get all metadata entries from the recycle bin.
     */
    public function getMetadata(Server $server): array
    {
        $this->fileRepository->setServer($server);

        try {
            $content = $this->fileRepository->getContent(self::METADATA_FILE);
            $data = json_decode($content, true);

            return $data['files'] ?? [];
        } catch (\Exception $e) {
            // If metadata file doesn't exist, return empty array
            return [];
        }
    }

    /**
     * Add a file entry to the metadata.
     */
    public function addEntry(Server $server, string $originalPath, string $recycleBinPath, string $originalName): void
    {
        $this->fileRepository->setServer($server);

        $metadata = $this->getMetadata($server);

        $metadata[] = [
            'originalPath' => $originalPath,
            'recycleBinPath' => $recycleBinPath,
            'originalName' => $originalName,
            'deletedAt' => Carbon::now()->toIso8601String(),
        ];

        $this->saveMetadata($server, $metadata);
    }

    /**
     * Remove entries from metadata by recycle bin paths.
     */
    public function removeEntries(Server $server, array $recycleBinPaths): void
    {
        $this->fileRepository->setServer($server);

        $metadata = $this->getMetadata($server);
        $metadata = array_filter($metadata, function ($entry) use ($recycleBinPaths) {
            return !in_array($entry['recycleBinPath'], $recycleBinPaths);
        });

        $this->saveMetadata($server, array_values($metadata));
    }

    /**
     * Get entries by recycle bin paths.
     */
    public function getEntriesByPaths(Server $server, array $recycleBinPaths): array
    {
        $metadata = $this->getMetadata($server);

        return array_filter($metadata, function ($entry) use ($recycleBinPaths) {
            return in_array($entry['recycleBinPath'], $recycleBinPaths);
        });
    }

    /**
     * Clear all metadata.
     */
    public function clearMetadata(Server $server): void
    {
        $this->fileRepository->setServer($server);
        $this->saveMetadata($server, []);
    }

    /**
     * Save metadata to file.
     */
    private function saveMetadata(Server $server, array $metadata): void
    {
        $this->fileRepository->setServer($server);

        $data = [
            'files' => $metadata,
        ];

        try {
            $this->fileRepository->putContent(self::METADATA_FILE, json_encode($data, JSON_PRETTY_PRINT));
        } catch (\Exception $e) {
            // If directory doesn't exist, we need to create it first
            // This will be handled by the controller when moving files
            throw $e;
        }
    }
}
