<?php

namespace Pterodactyl\Http\Controllers\Api\Client;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Models\Cloud\CloudFile;
use Pterodactyl\Models\Cloud\CloudFileShare;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Services\Cloud\CloudStorageService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CloudController extends ClientApiController
{
    public function __construct(private CloudStorageService $storage)
    {
        parent::__construct();
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $mine = CloudFile::query()->where('user_id', $user->id)->latest()->get();
        $shared = CloudFile::query()
            ->with('user:id,username')
            ->whereNotNull('share_token')
            ->whereIn('id', CloudFileShare::query()->where('user_id', $user->id)->select('file_id'))
            ->latest()
            ->get();

        return new JsonResponse([
            'enabled' => $this->storage->enabled(),
            'quota' => $this->storage->quotaBytes(),
            'used' => (int) $mine->sum('size'),
            'max_upload' => null,
            'files' => $mine->map(fn (CloudFile $f) => $this->transform($f, true))->values(),
            'shared' => $shared->map(fn (CloudFile $f) => $this->transform($f, false) + ['owner' => $f->user?->username])->values(),
        ]);
    }

    public function begin(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'size' => 'required|integer|min:1',
            'mime' => 'nullable|string|max:180',
        ]);

        return new JsonResponse($this->storage->beginDirectUpload($request->user(), $data['name'], (int) $data['size'], (string) ($data['mime'] ?? '')));
    }

    public function complete(Request $request, string $token): JsonResponse
    {
        $data = $request->validate(['file_id' => 'required|string|max:200']);
        $file = $this->storage->completeDirectUpload($request->user(), $token, $data['file_id']);

        return new JsonResponse($this->transform($file, true), 201);
    }

    public function destroy(Request $request, string $uuid): JsonResponse
    {
        $this->storage->delete($this->own($request, $uuid));

        return new JsonResponse([], 204);
    }

    public function share(Request $request, string $uuid): JsonResponse
    {
        $data = $request->validate(['enabled' => 'required|boolean']);
        $file = $this->storage->share($this->own($request, $uuid), (bool) $data['enabled']);

        return new JsonResponse($this->transform($file, true));
    }

    public function leave(Request $request, string $uuid): JsonResponse
    {
        $file = CloudFile::query()->where('uuid', $uuid)->first();
        if ($file) {
            CloudFileShare::query()->where('file_id', $file->id)->where('user_id', $request->user()->id)->delete();
        }

        return new JsonResponse([], 204);
    }

    private function own(Request $request, string $uuid): CloudFile
    {
        $file = CloudFile::query()->where('uuid', $uuid)->where('user_id', $request->user()->id)->first();
        if (!$file) {
            throw new NotFoundHttpException();
        }

        return $file;
    }

    private function transform(CloudFile $file, bool $owner): array
    {
        return [
            'uuid' => $file->uuid,
            'name' => $file->name,
            'mime' => $file->mime,
            'size' => $file->size,
            'source' => $file->source,
            'created_at' => $file->created_at?->toIso8601String(),
            'download_url' => '/cloud/files/' . $file->uuid . '/download',
            'share_url' => $owner ? $this->storage->shareUrl($file) : null,
        ];
    }
}
