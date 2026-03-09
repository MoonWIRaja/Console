<?php

namespace Pterodactyl\Services\Auth\OAuth;

use Carbon\CarbonImmutable;
use RuntimeException;
use InvalidArgumentException;
use Illuminate\Support\Arr;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class OAuthProviderService
{
    private const PROVIDERS = [
        'google' => [
            'label' => 'Google',
            'authorize_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
            'token_url' => 'https://oauth2.googleapis.com/token',
            'profile_url' => 'https://openidconnect.googleapis.com/v1/userinfo',
            'scopes' => ['openid', 'email', 'profile'],
            'authorize_params' => [
                'access_type' => 'online',
                'include_granted_scopes' => 'true',
                'prompt' => 'select_account',
            ],
        ],
        'discord' => [
            'label' => 'Discord',
            'authorize_url' => 'https://discord.com/api/oauth2/authorize',
            'token_url' => 'https://discord.com/api/oauth2/token',
            'profile_url' => 'https://discord.com/api/users/@me',
            'scopes' => ['identify', 'email', 'guilds.join'],
        ],
    ];

    public function isKnownProvider(string $provider): bool
    {
        return array_key_exists($this->normalizeProvider($provider), self::PROVIDERS);
    }

    public function label(string $provider): string
    {
        return $this->definition($provider)['label'];
    }

    public function isEnabled(string $provider): bool
    {
        return filter_var(config('services.' . $this->normalizeProvider($provider) . '.enabled', false), FILTER_VALIDATE_BOOLEAN);
    }

    public function isConfigured(string $provider): bool
    {
        $provider = $this->normalizeProvider($provider);

        return filled(config("services.$provider.client_id")) && filled(config("services.$provider.client_secret"));
    }

    public function isAvailable(string $provider): bool
    {
        return $this->isEnabled($provider) && $this->isConfigured($provider);
    }

    public function getFrontendProviders(): array
    {
        $providers = [];

        foreach (array_keys(self::PROVIDERS) as $provider) {
            $providers[$provider] = [
                'label' => $this->label($provider),
                'enabled' => $this->isAvailable($provider),
            ];
        }

        return $providers;
    }

    public function getManagementProviders(): array
    {
        $providers = [];

        foreach (array_keys(self::PROVIDERS) as $provider) {
            $providers[$provider] = [
                'label' => $this->label($provider),
                'enabled' => $this->isEnabled($provider),
                'configured' => $this->isConfigured($provider),
                'available' => $this->isAvailable($provider),
            ];
        }

        return $providers;
    }

    public function redirectUri(string $provider): string
    {
        return route('auth.oauth.callback', ['provider' => $this->normalizeProvider($provider)]);
    }

    public function authorizationUrl(string $provider, string $state): string
    {
        $provider = $this->normalizeProvider($provider);
        $definition = $this->definition($provider);

        $query = array_merge([
            'client_id' => config("services.$provider.client_id"),
            'redirect_uri' => $this->redirectUri($provider),
            'response_type' => 'code',
            'scope' => implode(' ', $definition['scopes']),
            'state' => $state,
        ], $definition['authorize_params'] ?? []);

        return $definition['authorize_url'] . '?' . Arr::query($query);
    }

    public function getIdentityFromCode(string $provider, string $code): array
    {
        $provider = $this->normalizeProvider($provider);
        $definition = $this->definition($provider);

        $tokenResponse = $this->http()
            ->asForm()
            ->post($definition['token_url'], [
                'grant_type' => 'authorization_code',
                'client_id' => config("services.$provider.client_id"),
                'client_secret' => config("services.$provider.client_secret"),
                'redirect_uri' => $this->redirectUri($provider),
                'code' => $code,
            ])
            ->throw()
            ->json();

        $token = $tokenResponse['access_token'] ?? null;

        if (!is_string($token) || $token === '') {
            throw new RuntimeException("Unable to retrieve an access token for [$provider].");
        }

        $profile = $this->http()
            ->withToken($token)
            ->get($definition['profile_url'])
            ->throw()
            ->json();

        if (!is_array($profile)) {
            throw new RuntimeException("Unable to retrieve a valid user profile for [$provider].");
        }

        return array_merge(
            $this->normalizeIdentity($provider, $profile),
            ['oauth_tokens' => $this->normalizeTokenPayload($tokenResponse)]
        );
    }

    public function refreshAccessToken(string $provider, string $refreshToken): array
    {
        $provider = $this->normalizeProvider($provider);
        $definition = $this->definition($provider);

        $response = $this->http()
            ->asForm()
            ->post($definition['token_url'], [
                'grant_type' => 'refresh_token',
                'client_id' => config("services.$provider.client_id"),
                'client_secret' => config("services.$provider.client_secret"),
                'refresh_token' => $refreshToken,
            ])
            ->throw()
            ->json();

        $tokens = $this->normalizeTokenPayload($response);
        if (!filled($tokens['access_token'] ?? null)) {
            throw new RuntimeException("Unable to refresh the access token for [$provider].");
        }

        if (!filled($tokens['refresh_token'] ?? null)) {
            $tokens['refresh_token'] = $refreshToken;
        }

        return $tokens;
    }

    private function definition(string $provider): array
    {
        $provider = $this->normalizeProvider($provider);

        if (!$this->isKnownProvider($provider)) {
            throw new InvalidArgumentException("The OAuth provider [$provider] is not supported.");
        }

        return self::PROVIDERS[$provider];
    }

    private function normalizeIdentity(string $provider, array $profile): array
    {
        $identity = match ($provider) {
            'google' => [
                'provider' => 'google',
                'provider_id' => (string) ($profile['sub'] ?? ''),
                'email' => filled($profile['email'] ?? null) ? (string) $profile['email'] : null,
                'display_name' => filled($profile['name'] ?? null) ? (string) $profile['name'] : null,
                'avatar' => filled($profile['picture'] ?? null) ? (string) $profile['picture'] : null,
            ],
            'discord' => [
                'provider' => 'discord',
                'provider_id' => (string) ($profile['id'] ?? ''),
                'email' => filled($profile['email'] ?? null) ? (string) $profile['email'] : null,
                'display_name' => $this->discordDisplayName($profile),
                'avatar' => $this->discordAvatarUrl($profile),
            ],
        };

        if ($identity['provider_id'] === '') {
            throw new RuntimeException("The OAuth provider [$provider] did not return a valid unique identifier.");
        }

        return $identity;
    }

    private function discordDisplayName(array $profile): ?string
    {
        foreach (['global_name', 'username'] as $key) {
            $value = $profile[$key] ?? null;
            if (filled($value)) {
                return (string) $value;
            }
        }

        return null;
    }

    private function discordAvatarUrl(array $profile): ?string
    {
        if (!filled($profile['id'] ?? null) || !filled($profile['avatar'] ?? null)) {
            return null;
        }

        return sprintf('https://cdn.discordapp.com/avatars/%s/%s.png?size=256', $profile['id'], $profile['avatar']);
    }

    private function http(): PendingRequest
    {
        return Http::acceptJson()
            ->timeout((int) config('pterodactyl.guzzle.timeout', 30))
            ->connectTimeout((int) config('pterodactyl.guzzle.connect_timeout', 10));
    }

    private function normalizeProvider(string $provider): string
    {
        return strtolower(trim($provider));
    }

    private function normalizeTokenPayload(array $payload): array
    {
        $expiresIn = isset($payload['expires_in']) ? (int) $payload['expires_in'] : null;

        return [
            'access_token' => filled($payload['access_token'] ?? null) ? (string) $payload['access_token'] : null,
            'refresh_token' => filled($payload['refresh_token'] ?? null) ? (string) $payload['refresh_token'] : null,
            'expires_at' => $expiresIn && $expiresIn > 0 ? CarbonImmutable::now()->addSeconds($expiresIn) : null,
        ];
    }
}
