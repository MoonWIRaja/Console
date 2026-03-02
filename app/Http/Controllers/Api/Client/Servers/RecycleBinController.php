<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Illuminate\Http\Response;
use Pterodactyl\Models\Server;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Services\RecycleBin\RecycleBinMetadataService;
use Pterodactyl\Http\Requests\Api\Client\Servers\RecycleBin\MoveToRecycleBinRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\RecycleBin\RecoverFilesRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\RecycleBin\EmptyRecycleBinRequest;

class RecycleBinController extends ClientApiController
{
    private const RECYCLE_BIN_DIR = '.recycle_bin';

    public function __construct(
        private DaemonFileRepository $fileRepository,
        private RecycleBinMetadataService $metadataService
    ) {
        parent::__construct();
    }

    /**
     * List all files in the recycle bin.
     *
     * @throws \Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException
     */
    public function list(Server $server): array
    {
        $this->fileRepository->setServer($server);
        $this->metadataService->getMetadata($server);

        try {
            $files = $this->fileRepository->getDirectory(self::RECYCLE_BIN_DIR);
        } catch (\Exception $e) {
            // If recycle bin doesn't exist, return empty array
            return ['data' => []];
        }

        $metadata = $this->metadataService->getMetadata($server);
        $metadataMap = [];
        foreach ($metadata as $entry) {
            $metadataMap[$entry['recycleBinPath']] = $entry;
        }

        // Filter out metadata.json and add metadata to each file
        $result = [];
        foreach ($files as $file) {
            if ($file['name'] === '.metadata.json') {
                continue;
            }

            $recycleBinPath = self::RECYCLE_BIN_DIR . '/' . $file['name'];
            if (isset($metadataMap[$recycleBinPath])) {
                $file['metadata'] = $metadataMap[$recycleBinPath];
            }

            $result[] = $file;
        }

        return ['data' => $result];
    }

    /**
     * Move files to recycle bin (move without compression).
     *
     * @throws \Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException
     */
    public function moveToRecycleBin(MoveToRecycleBinRequest $request, Server $server): JsonResponse
    {
        $this->fileRepository->setServer($server);
        $root = $request->input('root', '/');
        $files = $request->input('files', []);

        // Ensure recycle bin directory exists
        try {
            $this->fileRepository->getDirectory(self::RECYCLE_BIN_DIR);
        } catch (\Exception $e) {
            // Create recycle bin directory if it doesn't exist
            $this->fileRepository->createDirectory(self::RECYCLE_BIN_DIR, '/');
        }

        $timestamp = time();
        $movedFiles = [];

        // Move each file individually to recycle bin (without compression)
        foreach ($files as $file) {
            // Generate unique filename for recycle bin to avoid conflicts
            $fileExtension = pathinfo($file, PATHINFO_EXTENSION);
            $fileName = pathinfo($file, PATHINFO_FILENAME);
            $recycleBinFileName = $timestamp . '_' . $fileName . ($fileExtension ? '.' . $fileExtension : '');
            
            // Handle files with same name by adding counter
            $counter = 1;
            while (in_array($recycleBinFileName, array_column($movedFiles, 'recycleBinName'))) {
                $recycleBinFileName = $timestamp . '_' . $fileName . '_' . $counter . ($fileExtension ? '.' . $fileExtension : '');
                $counter++;
            }

            $originalPath = ($root === '/' ? '' : $root) . '/' . $file;
            if (substr($originalPath, 0, 1) === '/') {
                $originalPath = substr($originalPath, 1);
            }

            $recycleBinPath = self::RECYCLE_BIN_DIR . '/' . $recycleBinFileName;

            // Move file to recycle bin
            $this->fileRepository->renameFiles('/', [
                [
                    'from' => ($root === '/' ? '' : $root) . '/' . $file,
                    'to' => $recycleBinPath,
                ],
            ]);

            // Add to metadata for each file
            $this->metadataService->addEntry($server, $originalPath, $recycleBinPath, $file);

            $movedFiles[] = [
                'original' => $file,
                'recycleBinName' => $recycleBinFileName,
            ];

            // Small delay to ensure unique timestamps for files moved in quick succession
            usleep(1000); // 1ms delay
            $timestamp++;
        }

        Activity::event('server:file.move-to-recycle-bin')
            ->property('directory', $root)
            ->property('files', $files)
            ->log();

        return new JsonResponse([], Response::HTTP_NO_CONTENT);
    }

    /**
     * Recover files from recycle bin.
     *
     * @throws \Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException
     */
    public function recover(RecoverFilesRequest $request, Server $server): JsonResponse
    {
        $this->fileRepository->setServer($server);
        $recycleBinFiles = $request->input('files', []);

        $metadata = $this->metadataService->getMetadata($server);
        $metadataMap = [];
        foreach ($metadata as $entry) {
            $metadataMap[$entry['recycleBinPath']] = $entry;
        }

        foreach ($recycleBinFiles as $recycleBinFile) {
            $recycleBinPath = self::RECYCLE_BIN_DIR . '/' . $recycleBinFile;

            if (!isset($metadataMap[$recycleBinPath])) {
                continue;
            }

            $entry = $metadataMap[$recycleBinPath];
            $originalPath = $entry['originalPath'];
            $originalDir = dirname($originalPath);
            if ($originalDir === '.' || $originalDir === '') {
                $originalDir = '/';
            }
            // Ensure path starts with /
            if (substr($originalDir, 0, 1) !== '/') {
                $originalDir = '/' . $originalDir;
            }

            // Move file back to original location (no decompression needed)
            $fileName = basename($originalPath);
            $targetPath = rtrim($originalDir, '/') . '/' . $fileName;
            
            $this->fileRepository->renameFiles('/', [
                [
                    'from' => $recycleBinPath,
                    'to' => $targetPath,
                ],
            ]);

            // Remove from metadata
            $this->metadataService->removeEntries($server, [$recycleBinPath]);
        }

        Activity::event('server:file.recover-from-recycle-bin')
            ->property('files', $recycleBinFiles)
            ->log();

        return new JsonResponse([], Response::HTTP_NO_CONTENT);
    }

    /**
     * Recover all files from recycle bin.
     *
     * @throws \Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException
     */
    public function recoverAll(RecoverFilesRequest $request, Server $server): JsonResponse
    {
        $this->fileRepository->setServer($server);

        try {
            $files = $this->fileRepository->getDirectory(self::RECYCLE_BIN_DIR);
        } catch (\Exception $e) {
            return new JsonResponse([], Response::HTTP_NO_CONTENT);
        }

        $recycleBinFiles = [];
        foreach ($files as $file) {
            if ($file['name'] !== '.metadata.json') {
                $recycleBinFiles[] = $file['name'];
            }
        }

        if (empty($recycleBinFiles)) {
            return new JsonResponse([], Response::HTTP_NO_CONTENT);
        }

        $metadata = $this->metadataService->getMetadata($server);
        $metadataMap = [];
        foreach ($metadata as $entry) {
            $metadataMap[$entry['recycleBinPath']] = $entry;
        }

        foreach ($recycleBinFiles as $recycleBinFile) {
            $recycleBinPath = self::RECYCLE_BIN_DIR . '/' . $recycleBinFile;

            if (!isset($metadataMap[$recycleBinPath])) {
                continue;
            }

            $entry = $metadataMap[$recycleBinPath];
            $originalPath = $entry['originalPath'];
            $originalDir = dirname($originalPath);
            if ($originalDir === '.' || $originalDir === '') {
                $originalDir = '/';
            }
            if (substr($originalDir, 0, 1) !== '/') {
                $originalDir = '/' . $originalDir;
            }

            $fileName = basename($originalPath);
            $targetPath = rtrim($originalDir, '/') . '/' . $fileName;

            $this->fileRepository->renameFiles('/', [
                [
                    'from' => $recycleBinPath,
                    'to' => $targetPath,
                ],
            ]);

            $this->metadataService->removeEntries($server, [$recycleBinPath]);
        }

        Activity::event('server:file.recover-from-recycle-bin')
            ->property('files', $recycleBinFiles)
            ->log();

        return new JsonResponse([], Response::HTTP_NO_CONTENT);
    }

    /**
     * Empty recycle bin (permanently delete all files).
     *
     * @throws \Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException
     */
    public function empty(EmptyRecycleBinRequest $request, Server $server): JsonResponse
    {
        $this->fileRepository->setServer($server);

        try {
            $files = $this->fileRepository->getDirectory(self::RECYCLE_BIN_DIR);
        } catch (\Exception $e) {
            // Recycle bin doesn't exist, nothing to empty
            return new JsonResponse([], Response::HTTP_NO_CONTENT);
        }

        $filesToDelete = [];
        foreach ($files as $file) {
            $filesToDelete[] = $file['name'];
        }

        if (!empty($filesToDelete)) {
            $this->fileRepository->deleteFiles(self::RECYCLE_BIN_DIR, $filesToDelete);
        }

        // Clear metadata
        $this->metadataService->clearMetadata($server);

        Activity::event('server:file.empty-recycle-bin')->log();

        return new JsonResponse([], Response::HTTP_NO_CONTENT);
    }
}
