<?php

namespace Pterodactyl\Services\Cloud;

use Illuminate\Support\Str;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Cloud\CloudFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Pterodactyl\Models\Cloud\CloudAccount;
use Pterodactyl\Models\Cloud\CloudFileShare;
use Pterodactyl\Models\Cloud\CloudUserFolder;
use Pterodactyl\Exceptions\DisplayException;

class CloudStorageService
{
    private const ROOT_FOLDER = 'Burhan Cloud';
    private const QUOTA_REFRESH_MINUTES = 30;

    public function __construct(private GoogleDriveClient $drive)
    {
    }

    public function quotaBytes(): int
    {
        return (int) round(max(0.1, (float) config('cloud.quota_gb', 5)) * 1024 ** 3);
    }

    /**
     * Limit for files that pass through the panel server (Anney chat attachments).
     * Cloud Storage page uploads go straight to Google Drive and are limited only by quota.
     */
    public function maxUploadBytes(): int
    {
        return 100 * 1024 * 1024;
    }

    public function usedBytes(User $user): int
    {
        return (int) CloudFile::query()->where('user_id', $user->id)->sum('size');
    }

    public function enabled(): bool
    {
        return filter_var(config('cloud.enabled', false), FILTER_VALIDATE_BOOLEAN)
            && CloudAccount::query()->where('enabled', true)->exists();
    }

    public function upload(User $user, UploadedFile $file, string $source = CloudFile::SOURCE_UPLOAD): CloudFile
    {
        if (!$this->enabled()) {
            throw new DisplayException('Cloud storage is not available right now.');
        }

        $size = (int) $file->getSize();
        if ($size <= 0) {
            throw new DisplayException('The file is empty.');
        }
        if ($size > $this->maxUploadBytes()) {
            throw new DisplayException(sprintf('Chat attachments can be at most %d MB. Upload bigger files on the Cloud Storage page.', $this->maxUploadBytes() / 1048576));
        }

        $lock = cache()->lock('cloud-upload:' . $user->id, 900);
        if (!$lock->block(30)) {
            throw new DisplayException('Another upload is still being processed. Please wait a moment.');
        }

        try {
            if ($this->usedBytes($user) + $this->pendingBytes($user) + $size > $this->quotaBytes()) {
                throw new DisplayException(sprintf('Not enough space: this file needs %s but you have %s left of your %s.', $this->human($size), $this->human(max(0, $this->quotaBytes() - $this->usedBytes($user))), $this->human($this->quotaBytes())));
            }

            [$account, $folderId] = $this->folderFor($user, $size);
            $name = $this->cleanName((string) $file->getClientOriginalName());
            $mime = (string) ($file->getMimeType() ?: 'application/octet-stream');
            $driveId = $this->drive->upload($account, $file->getRealPath(), $name, $mime, $folderId);

            $account->increment('quota_usage', $size);

            return CloudFile::query()->create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $user->id,
                'account_id' => $account->id,
                'drive_file_id' => $driveId,
                'name' => $name,
                'mime' => Str::limit($mime, 180, ''),
                'size' => $size,
                'source' => $source,
            ]);
        } finally {
            $lock->release();
        }
    }

    /**
     * Starts a direct browser-to-Drive upload. Only the quota limits the file size.
     *
     * @return array{token: string, upload_url: string}
     */
    public function beginDirectUpload(User $user, string $name, int $size, string $mime): array
    {
        if (!$this->enabled()) {
            throw new DisplayException('Cloud storage is not available right now.');
        }
        if ($size <= 0) {
            throw new DisplayException('The file is empty.');
        }

        $left = $this->quotaBytes() - $this->usedBytes($user) - $this->pendingBytes($user);
        if ($size > $left) {
            throw new DisplayException(sprintf('Not enough space: this file needs %s but you have %s left of your %s.', $this->human($size), $this->human(max(0, $left)), $this->human($this->quotaBytes())));
        }

        [$account, $folderId] = $this->folderFor($user, $size);
        $name = $this->cleanName($name);
        $mime = Str::limit($mime !== '' ? $mime : 'application/octet-stream', 180, '');
        $url = $this->drive->startBrowserUpload($account, $name, $mime, $size, $folderId);

        $token = Str::random(40);
        cache()->put($this->uploadKey($token), [
            'user_id' => $user->id,
            'account_id' => $account->id,
            'folder_id' => $folderId,
            'name' => $name,
            'size' => $size,
            'mime' => $mime,
        ], now()->addDay());
        $this->trackPending($user, $token, $size);

        return ['token' => $token, 'upload_url' => $url];
    }

    /**
     * Confirms the file really landed in the user's folder with the declared size.
     */
    public function completeDirectUpload(User $user, string $token, string $driveFileId): CloudFile
    {
        $pending = cache()->get($this->uploadKey($token));
        if (!is_array($pending) || $pending['user_id'] !== $user->id) {
            throw new DisplayException('This upload session expired. Please upload the file again.');
        }

        $account = CloudAccount::query()->findOrFail($pending['account_id']);
        $meta = $this->drive->metadata($account, $driveFileId);

        if (!in_array($pending['folder_id'], $meta['parents'], true) || $meta['size'] !== $pending['size']) {
            $this->drive->delete($account, $driveFileId);
            throw new DisplayException('The uploaded file did not match what was expected, so it was removed.');
        }

        cache()->forget($this->uploadKey($token));
        $this->trackPending($user, $token, 0);

        if (CloudFile::query()->where('drive_file_id', $driveFileId)->exists()) {
            throw new DisplayException('This file was already saved.');
        }

        $account->increment('quota_usage', $pending['size']);

        return CloudFile::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'account_id' => $account->id,
            'drive_file_id' => $driveFileId,
            'name' => $pending['name'],
            'mime' => $pending['mime'],
            'size' => $pending['size'],
            'source' => CloudFile::SOURCE_UPLOAD,
        ]);
    }

    private function uploadKey(string $token): string
    {
        return 'cloud-direct-upload:' . $token;
    }

    /**
     * Space reserved by uploads that are still in progress, so parallel uploads cannot exceed the quota.
     */
    private function pendingBytes(User $user): int
    {
        return (int) array_sum(array_filter(
            (array) cache()->get('cloud-pending:' . $user->id, []),
            fn ($entry, $token) => cache()->has($this->uploadKey($token)),
            ARRAY_FILTER_USE_BOTH,
        ));
    }

    private function trackPending(User $user, string $token, int $size): void
    {
        $pending = (array) cache()->get('cloud-pending:' . $user->id, []);
        if ($size > 0) {
            $pending[$token] = $size;
        } else {
            unset($pending[$token]);
        }
        cache()->put('cloud-pending:' . $user->id, $pending, now()->addDay());
    }

    public function delete(CloudFile $file): void
    {
        $file->loadMissing('account');
        $this->drive->delete($file->account, $file->drive_file_id);
        $file->account->decrement('quota_usage', min($file->size, $file->account->quota_usage));
        $file->delete();
    }

    public function share(CloudFile $file, bool $enabled): CloudFile
    {
        $file->forceFill($enabled
            ? ['share_token' => $file->share_token ?: Str::random(40), 'shared_at' => $file->shared_at ?? now()]
            : ['share_token' => null, 'shared_at' => null])->save();

        if (!$enabled) {
            $file->shares()->delete();
        }

        return $file;
    }

    public function shareUrl(CloudFile $file): ?string
    {
        return $file->share_token ? rtrim((string) config('app.url'), '/') . '/cloud/s/' . $file->share_token : null;
    }

    /**
     * Opening a share link while logged in adds the file to the user's "Shared with me".
     */
    public function acceptShare(User $user, string $token): ?CloudFile
    {
        $file = CloudFile::query()->where('share_token', $token)->first();
        if (!$file) {
            return null;
        }

        if ($file->user_id !== $user->id) {
            CloudFileShare::query()->firstOrCreate(['file_id' => $file->id, 'user_id' => $user->id]);
        }

        return $file;
    }

    public function canDownload(User $user, CloudFile $file): bool
    {
        if ($file->user_id === $user->id || $user->root_admin) {
            return true;
        }

        return $file->share_token !== null
            && CloudFileShare::query()->where('file_id', $file->id)->where('user_id', $user->id)->exists();
    }

    public function stream(CloudFile $file)
    {
        $file->loadMissing('account');

        return $this->drive->download($file->account, $file->drive_file_id);
    }

    /**
     * Picks the Drive account and the user's folder on it. The user's existing
     * account is preferred; otherwise the enabled account with the most free space.
     *
     * @return array{0: CloudAccount, 1: string}
     */
    private function folderFor(User $user, int $size): array
    {
        $accounts = CloudAccount::query()->where('enabled', true)->orderBy('priority')->orderBy('id')->get()->map(function (CloudAccount $account) {
            if (!$account->quota_checked_at || $account->quota_checked_at->diffInMinutes(now()) >= self::QUOTA_REFRESH_MINUTES) {
                rescue(fn () => $this->drive->refreshQuota($account), null, false);
            }

            return $account;
        });

        $fits = fn (CloudAccount $a) => $a->quota_limit === null || $a->freeBytes() > $size + 50 * 1048576;
        $existing = CloudUserFolder::query()->where('user_id', $user->id)->pluck('drive_folder_id', 'account_id');

        // Admin-defined order: fill the first account, then move to the next when it is full.
        $account = $accounts->first($fits);

        if (!$account) {
            throw new DisplayException('Cloud storage is full right now. Please contact support.');
        }

        if ($existing->has($account->id)) {
            return [$account, $existing->get($account->id)];
        }

        return DB::transaction(function () use ($account, $user) {
            if (!$account->root_folder_id) {
                $account->update(['root_folder_id' => $this->drive->createFolder($account, self::ROOT_FOLDER)]);
            }

            $folderId = $this->drive->createFolder($account, 'user-' . $user->id, $account->root_folder_id);
            CloudUserFolder::query()->create(['user_id' => $user->id, 'account_id' => $account->id, 'drive_folder_id' => $folderId]);

            return [$account, $folderId];
        });
    }

    private function cleanName(string $name): string
    {
        $name = trim(preg_replace('/[\x00-\x1F\x7F\/\\\\]+/u', '_', $name) ?? '');

        return Str::limit($name !== '' ? $name : 'file', 200, '');
    }

    public function human(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $value = (float) $bytes;
        while ($value >= 1024 && $i < count($units) - 1) {
            $value /= 1024;
            ++$i;
        }

        return round($value, $i ? 1 : 0) . ' ' . $units[$i];
    }
}
