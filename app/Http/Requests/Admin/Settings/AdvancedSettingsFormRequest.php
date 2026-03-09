<?php

namespace Pterodactyl\Http\Requests\Admin\Settings;

use Pterodactyl\Http\Requests\Admin\AdminFormRequest;
use Illuminate\Validation\Validator;

class AdvancedSettingsFormRequest extends AdminFormRequest
{
    private const SECRET_KEYS = [
        'services:google:client_secret',
        'services:discord:client_secret',
        'services:discord:bot_token',
    ];

    /**
     * Return all the rules to apply to this request's data.
     */
    public function rules(): array
    {
        return [
            'turnstile:enabled' => 'required|in:true,false',
            'turnstile:secret_key' => 'nullable|required_if:turnstile:enabled,true|string|max:191',
            'turnstile:site_key' => 'nullable|required_if:turnstile:enabled,true|string|max:191',
            'turnstile:verify_domain' => 'required|in:true,false',
            'pterodactyl:guzzle:timeout' => 'required|integer|between:1,60',
            'pterodactyl:guzzle:connect_timeout' => 'required|integer|between:1,60',
            'pterodactyl:client_features:allocations:enabled' => 'required|in:true,false',
            'pterodactyl:client_features:allocations:range_start' => [
                'nullable',
                'required_if:pterodactyl:client_features:allocations:enabled,true',
                'integer',
                'between:1024,65535',
            ],
            'pterodactyl:client_features:allocations:range_end' => [
                'nullable',
                'required_if:pterodactyl:client_features:allocations:enabled,true',
                'integer',
                'between:1024,65535',
                'gt:pterodactyl:client_features:allocations:range_start',
            ],
            'services:google:enabled' => 'required|in:true,false',
            'services:google:client_id' => 'nullable|string|max:191',
            'services:google:client_secret' => 'nullable|string|max:191',
            'services:discord:enabled' => 'required|in:true,false',
            'services:discord:client_id' => 'nullable|string|max:191',
            'services:discord:client_secret' => 'nullable|string|max:191',
            'services:discord:community_enabled' => 'required|in:true,false',
            'services:discord:invite_url' => 'nullable|url|max:2048',
            'services:discord:guild_id' => ['nullable', 'regex:/^\d{17,20}$/'],
            'services:discord:role_id' => ['nullable', 'regex:/^\d{17,20}$/'],
            'services:discord:bot_token' => 'nullable|string|max:2048',
        ];
    }

    /**
     * Allow blank secret fields to preserve the current encrypted values.
     */
    public function normalize(?array $only = null): array
    {
        $values = parent::normalize($only);

        foreach (self::SECRET_KEYS as $key) {
            $value = $values[$key] ?? null;

            if (!is_string($value) || trim($value) === '') {
                unset($values[$key]);
            }
        }

        return $values;
    }

    public function withValidator($validator): void
    {
        $validator->after(function (Validator $validator) {
            foreach (['google', 'discord'] as $provider) {
                if (!$this->providerEnabled($provider)) {
                    continue;
                }

                if ($this->providerClientId($provider) === '') {
                    $validator->errors()->add(
                        "services:$provider:client_id",
                        "A {$this->providerLabel($provider)} client ID is required when OAuth login is enabled."
                    );
                }

                if ($this->providerClientSecret($provider) === '') {
                    $validator->errors()->add(
                        "services:$provider:client_secret",
                        "A {$this->providerLabel($provider)} client secret is required when OAuth login is enabled."
                    );
                }
            }

            if (!$this->discordCommunityEnabled()) {
                return;
            }

            if (!$this->providerEnabled('discord')) {
                $validator->errors()->add(
                    'services:discord:community_enabled',
                    'Discord OAuth login must be enabled before Discord community join can be enabled.'
                );
            }

            if ($this->providerClientId('discord') === '') {
                $validator->errors()->add(
                    'services:discord:client_id',
                    'A Discord client ID is required when Discord community join is enabled.'
                );
            }

            if ($this->providerClientSecret('discord') === '') {
                $validator->errors()->add(
                    'services:discord:client_secret',
                    'A Discord client secret is required when Discord community join is enabled.'
                );
            }

            foreach ([
                'services:discord:invite_url' => 'Discord invite URL',
                'services:discord:guild_id' => 'Discord guild ID',
                'services:discord:role_id' => 'Discord role ID',
                'services:discord:bot_token' => 'Discord bot token',
            ] as $key => $label) {
                if ($this->filledSetting($key)) {
                    continue;
                }

                $validator->errors()->add($key, "A {$label} is required when Discord community join is enabled.");
            }
        });
    }

    public function attributes(): array
    {
        return [
            'turnstile:enabled' => 'Turnstile Enabled',
            'turnstile:secret_key' => 'Turnstile Secret Key',
            'turnstile:site_key' => 'Turnstile Site Key',
            'turnstile:verify_domain' => 'Turnstile Verify Domain',
            'pterodactyl:guzzle:timeout' => 'HTTP Request Timeout',
            'pterodactyl:guzzle:connect_timeout' => 'HTTP Connection Timeout',
            'pterodactyl:client_features:allocations:enabled' => 'Auto Create Allocations Enabled',
            'pterodactyl:client_features:allocations:range_start' => 'Starting Port',
            'pterodactyl:client_features:allocations:range_end' => 'Ending Port',
            'services:google:enabled' => 'Google OAuth Enabled',
            'services:google:client_id' => 'Google Client ID',
            'services:google:client_secret' => 'Google Client Secret',
            'services:discord:enabled' => 'Discord OAuth Enabled',
            'services:discord:client_id' => 'Discord Client ID',
            'services:discord:client_secret' => 'Discord Client Secret',
            'services:discord:community_enabled' => 'Discord Community Join Enabled',
            'services:discord:invite_url' => 'Discord Invite URL',
            'services:discord:guild_id' => 'Discord Guild ID',
            'services:discord:role_id' => 'Discord Role ID',
            'services:discord:bot_token' => 'Discord Bot Token',
        ];
    }

    private function providerEnabled(string $provider): bool
    {
        return filter_var($this->input("services:$provider:enabled", 'false'), FILTER_VALIDATE_BOOLEAN);
    }

    private function providerClientId(string $provider): string
    {
        return trim((string) ($this->input("services:$provider:client_id") ?: config("services.$provider.client_id", '')));
    }

    private function providerClientSecret(string $provider): string
    {
        $value = trim((string) $this->input("services:$provider:client_secret", ''));
        if ($value === '!e') {
            return '';
        }

        if ($value !== '') {
            return $value;
        }

        return trim((string) config("services.$provider.client_secret", ''));
    }

    private function providerLabel(string $provider): string
    {
        return ucfirst($provider);
    }

    private function discordCommunityEnabled(): bool
    {
        return filter_var($this->input('services:discord:community_enabled', 'false'), FILTER_VALIDATE_BOOLEAN);
    }

    private function filledSetting(string $key): bool
    {
        $value = trim((string) $this->input($key, ''));
        if ($value === '!e') {
            return false;
        }

        if ($value !== '') {
            return true;
        }

        return filled(config(str_replace(':', '.', $key)));
    }
}
