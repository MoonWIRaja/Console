<?php

namespace Pterodactyl\Http\Requests\Admin\OAuth;

use Illuminate\Validation\Validator;
use Pterodactyl\Http\Requests\Admin\AdminFormRequest;

class UpdateOAuthSettingsRequest extends AdminFormRequest
{
    private const SECRET_KEYS = [
        'services:google:client_secret',
        'services:discord:client_secret',
    ];

    public function rules(): array
    {
        return [
            'services:google:enabled' => 'required|in:true,false',
            'services:google:client_id' => 'nullable|string|max:191',
            'services:google:client_secret' => 'nullable|string|max:191',
            'services:discord:enabled' => 'required|in:true,false',
            'services:discord:client_id' => 'nullable|string|max:191',
            'services:discord:client_secret' => 'nullable|string|max:191',
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
        });
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
}
