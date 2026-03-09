<?php

namespace Pterodactyl\Services\Discord;

use Throwable;
use Illuminate\Support\Arr;
use Pterodactyl\Models\User;
use RuntimeException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\PendingRequest;
use Pterodactyl\Models\UserOAuthAccount;
use Pterodactyl\Services\Auth\OAuth\OAuthProviderService;

class DiscordCommunityService
{
    private const API_BASE = 'https://discord.com/api';

    public function __construct(private OAuthProviderService $oauthProviders)
    {
    }

    public function getFrontendStatus(User $user): array
    {
        $account = $this->getDiscordAccount($user);
        $enabled = $this->isEnabled();
        $configured = $this->isConfigured();
        $oauthReady = $this->oauthProviders->isAvailable('discord');
        $available = $enabled && $configured && $oauthReady;
        $requiresRelink = $account && blank($account->access_token);

        $member = false;
        $roleAssigned = false;

        if ($available && $account && !$requiresRelink) {
            try {
                $memberData = $this->getGuildMember($account);
                $member = !is_null($memberData);
                $roleAssigned = $member && $this->memberHasConfiguredRole($memberData);
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return [
            'enabled' => $enabled,
            'configured' => $configured,
            'oauth_ready' => $oauthReady,
            'available' => $available,
            'linked' => !is_null($account),
            'requires_relink' => (bool) $requiresRelink,
            'invite_url' => $this->inviteUrl(),
            'member' => $member,
            'role_assigned' => $roleAssigned,
        ];
    }

    public function join(User $user): array
    {
        if (!$this->isEnabled()) {
            throw new RuntimeException('Discord community join is currently disabled.');
        }

        if (!$this->isConfigured()) {
            throw new RuntimeException('Discord community join is not fully configured yet.');
        }

        if (!$this->oauthProviders->isAvailable('discord')) {
            throw new RuntimeException('Discord OAuth must be enabled before users can join the Discord community.');
        }

        $account = $this->getDiscordAccount($user);
        if (!$account) {
            throw new RuntimeException('Link your Discord account first before joining the Discord community.');
        }

        if (blank($account->access_token)) {
            throw new RuntimeException('Reconnect your Discord account to refresh the permissions needed for community join.');
        }

        $account = $this->ensureFreshDiscordToken($account);
        $this->joinGuild($account);
        $this->assignRole($account);

        return [
            'redirect_url' => $this->inviteUrl(),
            'member' => true,
            'role_assigned' => true,
        ];
    }

    private function ensureFreshDiscordToken(UserOAuthAccount $account): UserOAuthAccount
    {
        if (!$account->token_expires_at || $account->token_expires_at->isFuture()) {
            return $account;
        }

        if (blank($account->refresh_token)) {
            throw new RuntimeException('Reconnect your Discord account to refresh the community join token.');
        }

        $tokens = $this->oauthProviders->refreshAccessToken('discord', $account->refresh_token);
        $account->forceFill([
            'access_token' => $tokens['access_token'] ?? null,
            'refresh_token' => $tokens['refresh_token'] ?? $account->refresh_token,
            'token_expires_at' => $tokens['expires_at'] ?? null,
        ])->saveOrFail();

        return $account->refresh();
    }

    private function joinGuild(UserOAuthAccount $account): void
    {
        $response = $this->botHttp()->put(sprintf(
            '%s/guilds/%s/members/%s',
            self::API_BASE,
            $this->guildId(),
            $account->provider_id
        ), [
            'access_token' => $account->access_token,
        ]);

        if ($response->successful()) {
            return;
        }

        throw new RuntimeException($this->discordErrorMessage(
            $response,
            'Unable to add this Discord user to the configured guild.',
            'The Discord bot is missing the permission required to add members to the configured guild.'
        ));
    }

    private function assignRole(UserOAuthAccount $account): void
    {
        $response = $this->botHttp()->put(sprintf(
            '%s/guilds/%s/members/%s/roles/%s',
            self::API_BASE,
            $this->guildId(),
            $account->provider_id,
            $this->roleId()
        ));

        if ($response->successful()) {
            return;
        }

        throw new RuntimeException($this->discordErrorMessage(
            $response,
            'Unable to assign the configured Discord role.',
            'The Discord bot needs Manage Roles permission and its highest role must be above the configured role.'
        ));
    }

    private function getGuildMember(UserOAuthAccount $account): ?array
    {
        $response = $this->botHttp()->get(sprintf(
            '%s/guilds/%s/members/%s',
            self::API_BASE,
            $this->guildId(),
            $account->provider_id
        ));

        if ($response->status() === 404) {
            return null;
        }

        if (!$response->successful()) {
            throw new RuntimeException($this->discordErrorMessage($response, 'Unable to retrieve the Discord member status.'));
        }

        $payload = $response->json();

        return is_array($payload) ? $payload : null;
    }

    private function memberHasConfiguredRole(?array $member): bool
    {
        if (!is_array($member)) {
            return false;
        }

        return in_array($this->roleId(), Arr::wrap($member['roles'] ?? []), true);
    }

    private function botHttp(): PendingRequest
    {
        return Http::acceptJson()
            ->withToken($this->botToken(), 'Bot')
            ->timeout((int) config('pterodactyl.guzzle.timeout', 30))
            ->connectTimeout((int) config('pterodactyl.guzzle.connect_timeout', 10));
    }

    private function discordErrorMessage(Response $response, string $fallback, ?string $missingPermissionsMessage = null): string
    {
        $body = $response->json();
        if (is_array($body) && filled($body['message'] ?? null)) {
            $message = (string) $body['message'];

            if ($message === 'Missing Permissions') {
                return $missingPermissionsMessage ?? 'The Discord bot is missing the permission required to complete this Discord action.';
            }

            return $message;
        }

        return $fallback;
    }

    private function getDiscordAccount(User $user): ?UserOAuthAccount
    {
        return $user->loadMissing('oauthAccounts')
            ->oauthAccounts
            ->first(fn (UserOAuthAccount $account) => $account->provider === 'discord');
    }

    private function isEnabled(): bool
    {
        return filter_var(config('services.discord.community_enabled', false), FILTER_VALIDATE_BOOLEAN);
    }

    private function isConfigured(): bool
    {
        return filled($this->inviteUrl())
            && filled($this->guildId())
            && filled($this->roleId())
            && filled($this->botToken());
    }

    private function inviteUrl(): ?string
    {
        $value = trim((string) config('services.discord.invite_url', ''));

        return $value !== '' ? $value : null;
    }

    private function guildId(): string
    {
        return trim((string) config('services.discord.guild_id', ''));
    }

    private function roleId(): string
    {
        return trim((string) config('services.discord.role_id', ''));
    }

    private function botToken(): string
    {
        return trim((string) config('services.discord.bot_token', ''));
    }
}
