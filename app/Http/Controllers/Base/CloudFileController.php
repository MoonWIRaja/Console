<?php

namespace Pterodactyl\Http\Controllers\Base;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Pterodactyl\Models\Cloud\CloudFile;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Cloud\CloudStorageService;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CloudFileController extends Controller
{
    public function __construct(private CloudStorageService $storage)
    {
    }

    /**
     * Guests are sent to login/sign-up first (auth middleware), then come back here.
     */
    public function share(Request $request, string $token): RedirectResponse
    {
        $file = $this->storage->acceptShare($request->user(), $token);

        return redirect($file ? '/cloud?section=shared&file=' . $file->uuid : '/cloud?section=shared&missing=1');
    }

    public function download(Request $request, string $uuid): StreamedResponse
    {
        $file = CloudFile::query()->where('uuid', $uuid)->first();
        if (!$file || !$this->storage->canDownload($request->user(), $file)) {
            throw new NotFoundHttpException();
        }

        $stream = $this->storage->stream($file);
        $name = str_replace(['"', "\r", "\n"], '', $file->name);

        return new StreamedResponse(function () use ($stream) {
            while (!$stream->eof()) {
                echo $stream->read(1024 * 256);
                flush();
            }
        }, 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Length' => (string) $file->size,
            'Content-Disposition' => 'attachment; filename="' . $name . '"; filename*=UTF-8\'\'' . rawurlencode($file->name),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
