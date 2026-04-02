<?php

namespace Pterodactyl\Http\Controllers\Auth;

use Throwable;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Pterodactyl\Models\User;
use Pterodactyl\Models\UserOAuthAccount;
use Pterodactyl\Services\Auth\SignupOnboardingService;
use Pterodactyl\Services\Auth\OAuth\OAuthProviderService;

class OAuthController extends AbstractLoginController
{
    private const SESSION_KEY = 'oauth_flow';

    public function __construct(
        private OAuthProviderService $providers,
        private SignupOnboardingService $signupOnboarding,
    )
    {
        parent::__construct();
    }

    public function redirect(Request $request, string $provider): RedirectResponse
    {
        if (!$this->providers->isKnownProvider($provider)) {
            abort(404);
        }

        $provider = strtolower($provider);
        $intent = $this->resolveIntent($request);

        if (!$this->providers->isAvailable($provider)) {
            return $this->redirectWithStatus($request, $intent, $provider, 'disabled');
        }

        if ($intent === 'link' && !$request->user()) {
            return $this->redirectWithStatus($request, $intent, $provider, 'login_required');
        }

        $signupUserId = null;
        if ($intent === 'signup') {
            $signupUser = $this->signupOnboarding->resolvePendingUser($request);
            if (!$signupUser || !$this->signupOnboarding->expectsProvider($signupUser, $provider)) {
                return $this->redirectWithStatus($request, $intent, $provider, 'invalid_state');
            }

            $signupUserId = $signupUser->id;
        }

        $request->session()->put(self::SESSION_KEY, [
            'provider' => $provider,
            'intent' => $intent,
            'state' => $state = bin2hex(random_bytes(20)),
            'user_id' => match ($intent) {
                'link' => $request->user()?->id,
                'signup' => $signupUserId,
                default => null,
            },
            'return_to' => $this->sanitizeReturnTo($request->query('return_to')),
            'expires_at' => CarbonImmutable::now()->addMinutes(10),
        ]);

        return redirect()->away($this->providers->authorizationUrl($provider, $state));
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        if (!$this->providers->isKnownProvider($provider)) {
            abort(404);
        }

        $provider = strtolower($provider);
        $flow = $request->session()->pull(self::SESSION_KEY);
        $intent = is_array($flow) ? (($flow['intent'] ?? null) ?: null) : null;

        if (!$this->hasValidFlow($flow, $provider, (string) $request->query('state', ''))) {
            return $this->redirectWithStatus($request, $intent, $provider, 'invalid_state', $flow);
        }

        if (!$this->providers->isAvailable($provider)) {
            return $this->redirectWithStatus($request, $intent, $provider, 'disabled', $flow);
        }

        if ($request->filled('error')) {
            return $this->redirectWithStatus($request, $intent, $provider, 'cancelled', $flow);
        }

        $code = (string) $request->query('code', '');
        if ($code === '') {
            return $this->redirectWithStatus($request, $intent, $provider, 'failed', $flow);
        }

        try {
            $identity = $this->providers->getIdentityFromCode($provider, $code);
        } catch (Throwable $exception) {
            report($exception);

            return $this->redirectWithStatus($request, $intent, $provider, 'failed', $flow);
        }

        if (($flow['intent'] ?? 'login') === 'link') {
            return $this->handleLinkedAccountCallback($request, $provider, (int) ($flow['user_id'] ?? 0), $identity, $flow);
        }

        if (($flow['intent'] ?? 'login') === 'signup') {
            return $this->handleSignupCallback($request, $provider, (int) ($flow['user_id'] ?? 0), $identity, $flow);
        }

        return $this->handleLoginCallback($request, $provider, $identity);
    }

    private function handleLoginCallback(Request $request, string $provider, array $identity): RedirectResponse
    {
        $account = UserOAuthAccount::query()
            ->with('user')
            ->where('provider', $provider)
            ->where('provider_id', $identity['provider_id'])
            ->first();

        if (!$account) {
            return $this->redirectWithStatus($request, 'login', $provider, 'not_linked');
        }

        $this->syncAccount($account, $identity);

        if ($this->signupOnboarding->isPending($account->user)) {
            $pendingSignup = $this->signupOnboarding->prepareForAuth($request, $account->user);
            $status = $pendingSignup['stage'] === SignupOnboardingService::STATE_PENDING_EMAIL_VERIFICATION
                ? 'email_verification_required'
                : 'signup_incomplete';

            return $this->redirectWithStatus($request, 'login', $provider, $status);
        }

        if (!$account->user->is_email_verified) {
            return $this->redirectWithStatus($request, 'login', $provider, 'email_verification_required');
        }

        if ($account->user->use_totp) {
            $token = $this->issueTwoFactorChallenge($account->user, $request);

            return redirect()->to('/auth/login/checkpoint?token=' . urlencode($token));
        }

        $this->completeLogin($account->user, $request);

        return redirect()->intended($this->redirectPath());
    }

    private function handleLinkedAccountCallback(Request $request, string $provider, int $userId, array $identity, ?array $flow = null): RedirectResponse
    {
        $user = $request->user();
        if (!$user || $user->id !== $userId) {
            return $this->redirectWithStatus($request, 'link', $provider, 'login_required');
        }

        $existing = UserOAuthAccount::query()
            ->where('provider', $provider)
            ->where('provider_id', $identity['provider_id'])
            ->first();

        if ($existing && $existing->user_id !== $user->id) {
            return $this->redirectWithStatus($request, 'link', $provider, 'conflict');
        }

        /** @var UserOAuthAccount $account */
        $account = $user->oauthAccounts()->firstOrNew(['provider' => $provider]);
        $this->syncAccount($account, $identity);

        return $this->redirectWithStatus($request, 'link', $provider, 'linked', $flow);
    }

    private function handleSignupCallback(Request $request, string $provider, int $userId, array $identity, ?array $flow = null): RedirectResponse
    {
        /** @var User|null $user */
        $user = User::query()->find($userId);
        if (!$user || !$this->signupOnboarding->isPending($user) || !$this->signupOnboarding->expectsProvider($user, $provider)) {
            return $this->redirectWithStatus($request, 'signup', $provider, 'invalid_state', $flow);
        }

        $this->signupOnboarding->remember($request, $user);

        $existing = UserOAuthAccount::query()
            ->where('provider', $provider)
            ->where('provider_id', $identity['provider_id'])
            ->first();

        if ($existing && $existing->user_id !== $user->id) {
            return $this->redirectWithStatus(
                $request,
                'signup',
                $provider,
                $provider === 'google' ? 'signup_google_conflict' : 'signup_discord_conflict',
                $flow
            );
        }

        if ($provider === 'google' && !$this->signupOnboarding->isGoogleEmailMatch($user, $identity['email'] ?? null)) {
            return $this->redirectWithStatus($request, 'signup', $provider, 'signup_google_email_mismatch', $flow);
        }

        /** @var UserOAuthAccount $account */
        $account = $user->oauthAccounts()->firstOrNew(['provider' => $provider]);
        $this->syncAccount($account, $identity);
        $this->signupOnboarding->advanceAfterProviderLink($request, $user, $provider);

        return $this->redirectWithStatus(
            $request,
            'signup',
            $provider,
            $provider === 'google' ? 'signup_google_linked' : 'signup_discord_linked',
            $flow
        );
    }

    private function syncAccount(UserOAuthAccount $account, array $identity): void
    {
        $tokens = is_array($identity['oauth_tokens'] ?? null) ? $identity['oauth_tokens'] : [];

        $account->forceFill([
            'provider' => $identity['provider'],
            'provider_id' => $identity['provider_id'],
            'email' => $identity['email'],
            'display_name' => $identity['display_name'],
            'avatar' => $identity['avatar'],
            'access_token' => $tokens['access_token'] ?? null,
            'refresh_token' => $tokens['refresh_token'] ?? null,
            'token_expires_at' => $tokens['expires_at'] ?? null,
        ])->saveOrFail();
    }

    private function hasValidFlow(mixed $flow, string $provider, string $state): bool
    {
        if (!is_array($flow)) {
            return false;
        }

        if (!in_array($flow['intent'] ?? null, ['login', 'link', 'signup'], true)) {
            return false;
        }

        if (!is_string($flow['provider'] ?? null) || !hash_equals($provider, $flow['provider'])) {
            return false;
        }

        if (!is_string($flow['state'] ?? null) || !hash_equals($flow['state'], $state)) {
            return false;
        }

        if (!($flow['expires_at'] ?? null) instanceof CarbonInterface) {
            return false;
        }

        return !$flow['expires_at']->isBefore(CarbonImmutable::now());
    }

    private function redirectWithStatus(Request $request, ?string $intent, string $provider, string $status, ?array $flow = null): RedirectResponse
    {
        $returnTo = is_array($flow) ? ($flow['return_to'] ?? null) : null;
        if ($intent === 'link' && $request->user() && is_string($returnTo) && $returnTo !== '') {
            return redirect()->to($this->appendQuery($returnTo, [
                'oauth_status' => $status,
                'oauth_provider' => $provider,
            ]));
        }

        $route = $intent === 'link' && $request->user() ? 'account' : 'auth.login';

        return redirect()->route($route, [
            'oauth_status' => $status,
            'oauth_provider' => $provider,
        ]);
    }

    private function resolveIntent(Request $request): string
    {
        $intent = strtolower((string) $request->query('intent', 'login'));

        return in_array($intent, ['login', 'link', 'signup'], true) ? $intent : 'login';
    }

    private function sanitizeReturnTo(?string $path): ?string
    {
        $path = trim((string) $path);
        if ($path === '' || !str_starts_with($path, '/')) {
            return null;
        }

        return $path;
    }

    private function appendQuery(string $url, array $query): string
    {
        $parts = parse_url($url);
        $existing = [];
        parse_str((string) Arr::get($parts, 'query', ''), $existing);

        $queryString = http_build_query(array_merge($existing, $query));
        $path = (string) Arr::get($parts, 'path', '/');

        return $path . ($queryString !== '' ? '?' . $queryString : '');
    }
}
