<?php

namespace Pterodactyl\Services\Admin\Settings;

use Illuminate\Contracts\Encryption\Encrypter;
use Pterodactyl\Providers\SettingsServiceProvider;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;

class AdminSettingsStoreService
{
    public function __construct(
        private SettingsRepositoryInterface $settings,
        private Encrypter $encrypter,
    ) {
    }

    public function save(array $values): void
    {
        foreach ($values as $key => $value) {
            $stored = $this->normalizeValue($key, $value);
            $runtime = $this->runtimeValue($key, $value);

            if ($stored === null && in_array($key, SettingsServiceProvider::getEncryptedKeys(), true)) {
                continue;
            }

            $this->settings->set('settings::' . $key, $stored);
            config()->set(str_replace(':', '.', $key), $runtime);
        }
    }

    private function normalizeValue(string $key, mixed $value): mixed
    {
        if (in_array($key, SettingsServiceProvider::getEncryptedKeys(), true)) {
            if ($value === '!e') {
                return '';
            }

            if (!is_string($value) || trim($value) === '') {
                return null;
            }

            return $this->encrypter->encrypt($value);
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        return is_null($value) ? null : (string) $value;
    }

    private function runtimeValue(string $key, mixed $value): mixed
    {
        if (in_array($key, SettingsServiceProvider::getEncryptedKeys(), true)) {
            if ($value === '!e') {
                return '';
            }

            return $value;
        }

        return $value;
    }
}
