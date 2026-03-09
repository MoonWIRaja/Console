<?php

namespace Pterodactyl\Http\Controllers\Auth;

use Throwable;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Pterodactyl\Models\UserOAuthAccount;
use Pterodactyl\Services\Auth\OAuth\OAuthProviderService;

class OAuthController extends AbstractLoginController
{
    private const SESSION_KEY = 'oauth_flow';

    public function __construct(private OAuthProviderService $providers)
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

        $request->session()->put(self::SESSION_KEY, [
            'provider' => $provider,
            'intent' => $intent,
            'state' => $state = bin2hex(random_bytes(20)),
            'user_id' => $intent === 'link' ? $request->user()?->id : null,
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
            return $this->redirectWithStatus($request, $intent, $provider, 'invalid_state');
        }

        if (!$this->providers->isAvailable($provider)) {
            return $this->redirectWithStatus($request, $intent, $provider, 'disabled');
        }

        if ($request->filled('error')) {
            return $this->redirectWithStatus($request, $intent, $provider, 'cancelled');
        }

        $code = (string) $request->query('code', '');
        if ($code === '') {
            return $this->redirectWithStatus($request, $intent, $provider, 'failed');
        }

        try {
            $identity = $this->providers->getIdentityFromCode($provider, $code);
        } catch (Throwable $exception) {
            report($exception);

            return $this->redirectWithStatus($request, $intent, $provider, 'failed');
        }

        if (($flow['intent'] ?? 'login') === 'link') {
            return $this->handleLinkedAccountCallback($request, $provider, (int) ($flow['user_id'] ?? 0), $identity);
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

    private function handleLinkedAccountCallback(Request $request, string $provider, int $userId, array $identity): RedirectResponse
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

        return $this->redirectWithStatus($request, 'link', $provider, 'linked');
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

        if (!in_array($flow['intent'] ?? null, ['login', 'link'], true)) {
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

    private function redirectWithStatus(Request $request, ?string $intent, string $provider, string $status): RedirectResponse
    {
        $route = $intent === 'link' && $request->user() ? 'account' : 'auth.login';

        return redirect()->route($route, [
            'oauth_status' => $status,
            'oauth_provider' => $provider,
        ]);
    }

    private function resolveIntent(Request $request): string
    {
        return $request->query('intent') === 'link' ? 'link' : 'login';
    }
}
