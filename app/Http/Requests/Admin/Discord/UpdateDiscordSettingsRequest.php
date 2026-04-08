<?php

namespace Pterodactyl\Http\Requests\Admin\Discord;

use Illuminate\Validation\Validator;
use Pterodactyl\Http\Requests\Admin\AdminFormRequest;

class UpdateDiscordSettingsRequest extends AdminFormRequest
{
    private const SECRET_KEYS = [
        'services:discord:bot_token',
    ];

    public function rules(): array
    {
        return [
            'services:discord:application_id' => ['nullable', 'regex:/^\d{17,20}$/'],
            'services:discord:application_public_key' => 'nullable|string|max:191',
            'services:discord:community_enabled' => 'required|in:true,false',
            'services:discord:guild_id' => ['nullable', 'regex:/^\d{17,20}$/'],
            'services:discord:role_id' => ['nullable', 'regex:/^\d{17,20}$/'],
            'services:discord:invite_url' => 'nullable|url|max:2048',
            'services:discord:bot_token' => 'nullable|string|max:2048',
        ];
    }

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
            if (!$this->communityEnabled()) {
                return;
            }

            if (!filter_var(config('services.discord.enabled', false), FILTER_VALIDATE_BOOLEAN)) {
                $validator->errors()->add(
                    'services:discord:community_enabled',
                    'Discord OAuth login must be enabled in /admin/oauth before Discord community join can be enabled.'
                );
            }

            if (!filled(config('services.discord.client_id')) || !filled(config('services.discord.client_secret'))) {
                $validator->errors()->add(
                    'services:discord:community_enabled',
                    'Discord OAuth client ID and client secret must be configured in /admin/oauth before Discord community join can be enabled.'
                );
            }

            foreach ([
                'services:discord:guild_id' => 'Discord guild ID',
                'services:discord:role_id' => 'Discord role ID',
                'services:discord:invite_url' => 'Discord invite URL',
                'services:discord:bot_token' => 'Discord bot token',
            ] as $key => $label) {
                if ($this->filledInput($key)) {
                    continue;
                }

                $validator->errors()->add($key, "A {$label} is required when Discord community join is enabled.");
            }
        });
    }

    private function communityEnabled(): bool
    {
        return filter_var($this->input('services:discord:community_enabled', 'false'), FILTER_VALIDATE_BOOLEAN);
    }

    private function filledInput(string $key): bool
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
