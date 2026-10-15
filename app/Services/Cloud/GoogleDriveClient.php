<?php

namespace Pterodactyl\Services\Cloud;

use GuzzleHttp\Client;
use Psr\Http\Message\StreamInterface;
use Pterodactyl\Models\Cloud\CloudAccount;
use Pterodactyl\Exceptions\DisplayException;

/**
 * Minimal Google Drive v3 client using the panel's Google OAuth client and the
 * drive.file scope (the panel only sees files it created).
 */
class GoogleDriveClient
{
    public const SCOPES = 'openid email https://www.googleapis.com/auth/drive.file';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const API = 'https://www.googleapis.com/drive/v3';
    private const UPLOAD = 'https://www.googleapis.com/upload/drive/v3/files';
    private const FOLDER_MIME = 'application/vnd.google-apps.folder';

    private function http(int $timeout = 60): Client
    {
        return new Client(['timeout' => $timeout, 'connect_timeout' => 10, 'http_errors' => false]);
    }

    public static function redirectUri(): string
    {
        // Reuses the sign-in callback already registered on the Google OAuth client;
        // OAuthController hands it to the cloud connector when the state matches.
        return rtrim((string) config('app.url'), '/') . '/oauth/google/callback';
    }

    public function authUrl(string $state): string
    {
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => self::redirectUri(),
            'response_type' => 'code',
            'scope' => self::SCOPES,
            'access_type' => 'offline',
            'prompt' => 'consent select_account',
            'include_granted_scopes' => 'true',
            'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * @return array{email: string, access_token: string, refresh_token: string, expires_in: int}
     */
    public function exchangeCode(string $code): array
    {
        $response = $this->http()->post(self::TOKEN_URL, ['form_params' => [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'redirect_uri' => self::redirectUri(),
        ]]);
        $data = json_decode((string) $response->getBody(), true) ?: [];

        if (empty($data['access_token'])) {
            throw new DisplayException('Google did not return a token: ' . ($data['error_description'] ?? $data['error'] ?? ('HTTP ' . $response->getStatusCode())));
        }
        if (empty($data['refresh_token'])) {
            throw new DisplayException('Google did not return a refresh token. Remove the panel from https://myaccount.google.com/permissions for this account and connect again.');
        }

        $info = json_decode((string) $this->http()->get('https://openidconnect.googleapis.com/v1/userinfo', [
            'headers' => ['Authorization' => 'Bearer ' . $data['access_token']],
        ])->getBody(), true) ?: [];

        return [
            'email' => (string) ($info['email'] ?? 'unknown'),
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'],
            'expires_in' => (int) ($data['expires_in'] ?? 3600),
        ];
    }

    public function token(CloudAccount $account): string
    {
        if ($account->access_token && $account->token_expires_at && $account->token_expires_at->isAfter(now()->addMinutes(2))) {
            return $account->access_token;
        }

        $response = $this->http()->post(self::TOKEN_URL, ['form_params' => [
            'grant_type' => 'refresh_token',
            'refresh_token' => $account->refresh_token,
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
        ]]);
        $data = json_decode((string) $response->getBody(), true) ?: [];

        if (empty($data['access_token'])) {
            $account->update(['last_error' => 'Token refresh failed (' . ($data['error'] ?? $response->getStatusCode()) . '). Reconnect this Google account.']);

            throw new DisplayException('Cloud storage is temporarily unavailable. Please try again later.');
        }

        $account->forceFill([
            'access_token' => $data['access_token'],
            'token_expires_at' => now()->addSeconds((int) ($data['expires_in'] ?? 3600)),
            'last_error' => null,
        ])->save();

        return $data['access_token'];
    }

    private function request(CloudAccount $account, string $method, string $url, array $options = [], int $timeout = 60)
    {
        $options['headers'] = ($options['headers'] ?? []) + ['Authorization' => 'Bearer ' . $this->token($account)];

        return $this->http($timeout)->request($method, $url, $options);
    }

    private function json($response, string $what): array
    {
        $data = json_decode((string) $response->getBody(), true);
        if ($response->getStatusCode() >= 400 || !is_array($data)) {
            $reason = is_array($data) ? (string) ($data['error']['message'] ?? '') : '';
            if (str_contains($reason, 'has not been used in project') || str_contains($reason, 'is disabled')) {
                $reason = 'The Google Drive API is not enabled for this Google Cloud project. Enable it in Google Cloud Console, then try again.';
            }

            throw new DisplayException(sprintf('Google Drive %s failed (HTTP %d)%s', $what, $response->getStatusCode(), $reason !== '' ? ': ' . \Illuminate\Support\Str::limit($reason, 220) : '.'));
        }

        return $data;
    }

    public function createFolder(CloudAccount $account, string $name, ?string $parent = null): string
    {
        $body = ['name' => $name, 'mimeType' => self::FOLDER_MIME];
        if ($parent) {
            $body['parents'] = [$parent];
        }

        return (string) $this->json($this->request($account, 'POST', self::API . '/files?fields=id', ['json' => $body]), 'folder creation')['id'];
    }

    public function refreshQuota(CloudAccount $account): CloudAccount
    {
        $response = $this->request($account, 'GET', self::API . '/about?fields=storageQuota,user');
        if ($response->getStatusCode() >= 400) {
            $error = json_decode((string) $response->getBody(), true)['error']['message'] ?? ('HTTP ' . $response->getStatusCode());
            $account->forceFill(['last_error' => 'Quota check failed: ' . \Illuminate\Support\Str::limit((string) $error, 300)])->save();
        }
        $data = $this->json($response, 'quota check');
        $quota = $data['storageQuota'] ?? [];

        $account->forceFill([
            'quota_limit' => isset($quota['limit']) ? (int) $quota['limit'] : null,
            'quota_usage' => (int) ($quota['usage'] ?? 0),
            'quota_checked_at' => now(),
            'last_error' => null,
        ])->save();

        return $account;
    }

    /**
     * Uploads a local file with the resumable protocol and returns the Drive file id.
     */
    public function upload(CloudAccount $account, string $path, string $name, string $mime, string $folderId): string
    {
        $size = filesize($path);
        $init = $this->request($account, 'POST', self::UPLOAD . '?uploadType=resumable&fields=id', [
            'headers' => [
                'Content-Type' => 'application/json; charset=UTF-8',
                'X-Upload-Content-Type' => $mime,
                'X-Upload-Content-Length' => (string) $size,
            ],
            'json' => ['name' => $name, 'parents' => [$folderId]],
        ]);

        $session = $init->getHeaderLine('Location');
        if ($init->getStatusCode() >= 400 || $session === '') {
            throw new DisplayException('Google Drive refused the upload (HTTP ' . $init->getStatusCode() . ').');
        }

        $handle = fopen($path, 'rb');
        try {
            $response = $this->http(1800)->put($session, [
                'headers' => ['Content-Type' => $mime, 'Content-Length' => (string) $size],
                'body' => $handle,
            ]);
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }

        return (string) $this->json($response, 'upload')['id'];
    }

    /**
     * Opens a resumable upload session the browser can send the file to directly.
     * The Origin header makes Google allow the browser's cross-origin PUTs.
     */
    public function startBrowserUpload(CloudAccount $account, string $name, string $mime, int $size, string $folderId): string
    {
        $response = $this->request($account, 'POST', self::UPLOAD . '?uploadType=resumable&fields=id,size,parents', [
            'headers' => [
                'Content-Type' => 'application/json; charset=UTF-8',
                'X-Upload-Content-Type' => $mime,
                'X-Upload-Content-Length' => (string) $size,
                'Origin' => rtrim((string) config('app.url'), '/'),
            ],
            'json' => ['name' => $name, 'parents' => [$folderId]],
        ]);

        $session = $response->getHeaderLine('Location');
        if ($response->getStatusCode() >= 400 || $session === '') {
            throw new DisplayException('Google Drive refused the upload (HTTP ' . $response->getStatusCode() . ').');
        }

        return $session;
    }

    /**
     * @return array{id: string, name: string, size: int, parents: array, mimeType: string}
     */
    public function metadata(CloudAccount $account, string $fileId): array
    {
        $data = $this->json($this->request($account, 'GET', self::API . '/files/' . rawurlencode($fileId) . '?fields=id,name,size,parents,mimeType,trashed'), 'file lookup');

        return [
            'id' => (string) $data['id'],
            'name' => (string) ($data['name'] ?? ''),
            'size' => (int) ($data['size'] ?? 0),
            'parents' => (array) ($data['parents'] ?? []),
            'mimeType' => (string) ($data['mimeType'] ?? 'application/octet-stream'),
        ];
    }

    public function download(CloudAccount $account, string $fileId): StreamInterface
    {
        $response = $this->request($account, 'GET', self::API . '/files/' . rawurlencode($fileId) . '?alt=media', ['stream' => true], 1800);
        if ($response->getStatusCode() >= 400) {
            throw new DisplayException('The file could not be downloaded from storage (HTTP ' . $response->getStatusCode() . ').');
        }

        return $response->getBody();
    }

    public function delete(CloudAccount $account, string $fileId): void
    {
        $response = $this->request($account, 'DELETE', self::API . '/files/' . rawurlencode($fileId));
        if ($response->getStatusCode() >= 400 && $response->getStatusCode() !== 404) {
            throw new DisplayException('The file could not be deleted from storage (HTTP ' . $response->getStatusCode() . ').');
        }
    }
}
