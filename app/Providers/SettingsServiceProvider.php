<?php

namespace Pterodactyl\Providers;

use Psr\Log\LoggerInterface as Log;
use Illuminate\Database\QueryException;
use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;

class SettingsServiceProvider extends ServiceProvider
{
    /**
     * An array of configuration keys to override with database values
     * if they exist.
     */
    protected array $keys = [
        'app:name',
        'app:logo',
        'app:locale',
        'turnstile:enabled',
        'turnstile:secret_key',
        'turnstile:site_key',
        'turnstile:verify_domain',
        'pterodactyl:guzzle:timeout',
        'pterodactyl:guzzle:connect_timeout',
        'pterodactyl:console:count',
        'pterodactyl:console:frequency',
        'pterodactyl:auth:2fa_required',
        'pterodactyl:client_features:allocations:enabled',
        'pterodactyl:client_features:allocations:range_start',
        'pterodactyl:client_features:allocations:range_end',
        'services:google:enabled',
        'services:google:client_id',
        'services:google:client_secret',
        'services:discord:enabled',
        'services:discord:client_id',
        'services:discord:client_secret',
        'services:discord:community_enabled',
        'services:discord:invite_url',
        'services:discord:guild_id',
        'services:discord:role_id',
        'services:discord:bot_token',
        'billing:currency',
        'billing:invoice_lead_days',
        'billing:suspend_grace_hours',
        'billing:delete_grace_hours',
        'billing:fiuu:enabled',
        'billing:fiuu:sandbox',
        'billing:fiuu:merchant_id',
        'billing:fiuu:verify_key',
        'billing:fiuu:secret_key',
        'billing:fiuu:return_url',
        'billing:fiuu:callback_url',
        'billing:fiuu:requery_url',
        'billing:fiuu:recurring_url',
        'billing:fiuu:refund_url',
        'billing:fiuu:enabled_methods',
    ];

    /**
     * Keys specific to the mail driver that are only grabbed from the database
     * when using the SMTP driver.
     */
    protected array $emailKeys = [
        'mail:mailers:smtp:host',
        'mail:mailers:smtp:port',
        'mail:mailers:smtp:encryption',
        'mail:mailers:smtp:username',
        'mail:mailers:smtp:password',
        'mail:from:address',
        'mail:from:name',
    ];

    /**
     * Keys that are encrypted and should be decrypted when set in the
     * configuration array.
     */
    protected static array $encrypted = [
        'mail:mailers:smtp:password',
        'services:google:client_secret',
        'services:discord:client_secret',
        'services:discord:bot_token',
        'billing:fiuu:verify_key',
        'billing:fiuu:secret_key',
    ];

    /**
     * Boot the service provider.
     */
    public function boot(ConfigRepository $config, Encrypter $encrypter, Log $log, SettingsRepositoryInterface $settings): void
    {
        // Only set the email driver settings from the database if we
        // are configured using SMTP as the driver.
        if ($config->get('mail.default') === 'smtp') {
            $this->keys = array_merge($this->keys, $this->emailKeys);
        }

        try {
            $values = $settings->all()->mapWithKeys(function ($setting) {
                return [$setting->key => $setting->value];
            })->toArray();
        } catch (QueryException $exception) {
            $log->notice('A query exception was encountered while trying to load settings from the database: ' . $exception->getMessage());

            return;
        }

        foreach ($this->keys as $key) {
            $configKey = str_replace(':', '.', $key);
            $defaultValue = $config->get($configKey);
            $value = array_get($values, 'settings::' . $key, $defaultValue);
            if (in_array($key, self::$encrypted)) {
                try {
                    $value = $encrypter->decrypt($value);
                } catch (DecryptException $exception) {
                }
            }

            if (is_string($value)) {
                switch (strtolower($value)) {
                    case 'true':
                    case '(true)':
                        $value = true;
                        break;
                    case 'false':
                    case '(false)':
                        $value = false;
                        break;
                    case 'empty':
                    case '(empty)':
                        $value = '';
                        break;
                    case 'null':
                    case '(null)':
                        $value = null;
                }
            }

            if (is_array($defaultValue) && is_string($value)) {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $value = $decoded;
                } else {
                    $value = array_values(array_filter(array_map('trim', explode(',', $value))));
                }
            }

            $config->set($configKey, $value);
        }
    }

    public static function getEncryptedKeys(): array
    {
        return self::$encrypted;
    }
}
