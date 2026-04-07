<?php

namespace Pterodactyl\Services\Auth;

use Illuminate\Http\Request;
use Pterodactyl\Models\User;
use Pterodactyl\Services\Auth\OAuth\OAuthProviderService;

class SignupOnboardingService
{
    public const SESSION_KEY = 'pending_signup';

    public const STATE_COMPLETE = 'complete';
    public const STATE_PENDING_GOOGLE_LINK = 'pending_google_link';
    public const STATE_PENDING_DISCORD_LINK = 'pending_discord_link';
    public const STATE_PENDING_EMAIL_VERIFICATION = 'pending_email_verification';

    public function __construct(
        private EmailVerificationService $emailVerificationService,
        private OAuthProviderService $oauthProviders,
    ) {
    }

    public function shouldUseProviderOnboarding(): bool
    {
        return $this->oauthProviders->isAvailable('google') && $this->oauthProviders->isAvailable('discord');
    }

    public function initialState(): string
    {
        return $this->shouldUseProviderOnboarding()
            ? self::STATE_PENDING_GOOGLE_LINK
            : self::STATE_PENDING_EMAIL_VERIFICATION;
    }

    public function begin(Request $request, User $user): array
    {
        $user->forceFill([
            'signup_onboarding_state' => $this->initialState(),
        ])->save();

        return $this->prepareForAuth($request, $user);
    }

    public function prepareForAuth(Request $request, User $user): array
    {
        $this->remember($request, $user);

        $verificationToken = $user->signup_onboarding_state === self::STATE_PENDING_EMAIL_VERIFICATION
            ? $this->ensureVerificationToken($request, $user)
            : null;

        return $this->toFrontendPayload($request, $user, $verificationToken);
    }

    public function current(Request $request): ?array
    {
        $user = $this->resolvePendingUser($request);

        return $user ? $this->prepareForAuth($request, $user) : null;
    }

    public function resolvePendingUser(Request $request): ?User
    {
        $details = $request->session()->get(self::SESSION_KEY);
        $userId = is_array($details) ? (int) ($details['user_id'] ?? 0) : 0;

        if ($userId < 1) {
            return null;
        }

        /** @var User|null $user */
        $user = User::query()->find($userId);
        if (!$user || !$this->isPending($user)) {
            $this->clear($request);

            return null;
        }

        return $user;
    }

    public function remember(Request $request, User $user): void
    {
        $request->session()->put(self::SESSION_KEY, [
            'user_id' => $user->id,
        ]);
    }

    public function clear(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }

    public function isPending(User $user): bool
    {
        return in_array($user->signup_onboarding_state, [
            self::STATE_PENDING_GOOGLE_LINK,
            self::STATE_PENDING_DISCORD_LINK,
            self::STATE_PENDING_EMAIL_VERIFICATION,
        ], true);
    }

    public function expectsProvider(User $user, string $provider): bool
    {
        return match (strtolower($provider)) {
            'google' => $user->signup_onboarding_state === self::STATE_PENDING_GOOGLE_LINK,
            'discord' => $user->signup_onboarding_state === self::STATE_PENDING_DISCORD_LINK,
            default => false,
        };
    }

    public function advanceAfterProviderLink(Request $request, User $user, string $provider): array
    {
        $nextState = match (strtolower($provider)) {
            'google' => self::STATE_PENDING_DISCORD_LINK,
            'discord' => self::STATE_PENDING_EMAIL_VERIFICATION,
            default => self::STATE_PENDING_EMAIL_VERIFICATION,
        };

        $user->forceFill([
            'signup_onboarding_state' => $nextState,
        ])->save();

        return $this->prepareForAuth($request, $user);
    }

    public function complete(Request $request, User $user): void
    {
        $user->forceFill([
            'signup_onboarding_state' => self::STATE_COMPLETE,
        ])->save();

        $this->clear($request);
    }

    public function isGoogleEmailMatch(User $user, ?string $providerEmail): bool
    {
        if (!filled($providerEmail)) {
            return false;
        }

        return mb_strtolower(trim($user->email)) === mb_strtolower(trim((string) $providerEmail));
    }

    private function toFrontendPayload(Request $request, User $user, ?string $verificationToken = null): array
    {
        $user->loadMissing('oauthAccounts');

        return [
            'stage' => $user->signup_onboarding_state,
            'email' => $user->email,
            'verification_token' => $verificationToken ?? $this->sessionVerificationToken($request, $user),
            'google_link_url' => route('auth.oauth.redirect', ['provider' => 'google', 'intent' => 'signup']),
            'discord_link_url' => route('auth.oauth.redirect', ['provider' => 'discord', 'intent' => 'signup']),
            'google_linked' => $this->hasLinkedProvider($user, 'google'),
            'discord_linked' => $this->hasLinkedProvider($user, 'discord'),
            'google_available' => $this->oauthProviders->isAvailable('google'),
            'discord_available' => $this->oauthProviders->isAvailable('discord'),
        ];
    }

    private function ensureVerificationToken(Request $request, User $user): string
    {
        $existing = $this->sessionVerificationToken($request, $user);
        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        return $this->emailVerificationService->issueChallenge($request, $user);
    }

    private function sessionVerificationToken(Request $request, User $user): ?string
    {
        $details = $request->session()->get('email_verification_token');
        if (!is_array($details) || !$this->emailVerificationService->hasValidSessionData($details)) {
            return null;
        }

        if ((int) ($details['user_id'] ?? 0) !== $user->id) {
            return null;
        }

        return is_string($details['token_value'] ?? null) ? $details['token_value'] : null;
    }

    private function hasLinkedProvider(User $user, string $provider): bool
    {
        return $user->oauthAccounts->contains(fn ($account) => $account->provider === $provider);
    }
}
