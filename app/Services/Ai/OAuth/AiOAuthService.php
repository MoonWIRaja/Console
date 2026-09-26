<?php

namespace Pterodactyl\Services\Ai\OAuth;

use GuzzleHttp\Client;
use Illuminate\Support\Str;
use Pterodactyl\Models\Ai\AiProvider;
use Illuminate\Support\Facades\Cache;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Services\Ai\Catalog\ProviderCatalog;

/**
 * OAuth login flows and token refresh, ported from 9router's src/lib/oauth
 * and open-sse/services/tokenRefresh (MIT License, (c) decolua and contributors).
 *
 * Flows that normally rely on a localhost callback (Codex, xAI, Google) use the
 * "paste the redirected URL" method, because the panel runs on a remote server.
 */
class AiOAuthService
{
    private const SESSION_TTL = 900;
    private const GOOGLE_TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const GOOGLE_REDIRECT = 'http://localhost:8085/oauth2callback';
    private const GROK_UA = 'grok-pager/0.2.93 grok-shell/0.2.93 (linux; x86_64)';
    private const XAI = [
        'authorizeUrl' => 'https://auth.x.ai/oauth2/authorize',
        'tokenUrl' => 'https://auth.x.ai/oauth2/token',
        'scope' => 'openid profile email offline_access grok-cli:access api:access',
        'redirectUri' => 'http://127.0.0.1:56121/callback',
    ];

    private function http(int $timeout = 30): Client
    {
        return new Client(['timeout' => $timeout, 'connect_timeout' => 10, 'http_errors' => false]);
    }

    private function definition(string $id): array
    {
        $definition = ProviderCatalog::get($id);
        if (!$definition) {
            throw new DisplayException('Unknown provider: ' . $id);
        }

        if ($secret = config('ai.oauth_client_secrets.' . $id)) {
            $definition['oauth']['clientSecret'] = $secret;
        }

        return $definition;
    }

    /**
     * Starts a login. Returns what the admin UI needs to show.
     */
    public function start(string $id, string $callbackBase): array
    {
        $definition = $this->definition($id);
        $sessionId = Str::random(40);
        $oauth = $definition['oauth'];
        $session = ['provider' => $id, 'flow' => $definition['flow']];

        switch ($definition['flow']) {
            case 'device':
                $device = $this->requestDeviceCode($id, $oauth);
                $session += [
                    'device_code' => $device['device_code'],
                    'interval' => (int) ($device['interval'] ?? 5),
                    'extra' => $device['extra'] ?? [],
                ];
                $view = [
                    'type' => 'device',
                    'user_code' => $device['user_code'] ?? null,
                    'verification_uri' => $device['verification_uri_complete'] ?? $device['verification_uri'] ?? null,
                    'interval' => $session['interval'],
                ];
                break;

            case 'kilocode':
                $response = $this->http()->post($oauth['initiateUrl'], ['headers' => ['Content-Type' => 'application/json']]);
                $data = $this->json($response, 'Kilo Code device auth failed');
                $session += ['device_code' => $data['code'], 'interval' => 3];
                $view = ['type' => 'device', 'user_code' => $data['code'], 'verification_uri' => $data['verificationUrl'], 'interval' => 3];
                break;

            case 'pkce_paste':
                $verifier = $this->base64Url(random_bytes($id === 'xai' ? 96 : 32));
                $state = Str::random(32);
                $session += ['verifier' => $verifier, 'state' => $state];
                $view = ['type' => 'paste', 'auth_url' => $this->pkceAuthUrl($id, $oauth, $state, $this->pkceChallenge($verifier))];
                break;

            case 'google_paste':
                $state = Str::random(32);
                $session += ['state' => $state];
                $view = ['type' => 'paste', 'auth_url' => $oauth['authorizeUrl'] . '?' . http_build_query([
                    'client_id' => $oauth['clientId'],
                    'response_type' => 'code',
                    'redirect_uri' => self::GOOGLE_REDIRECT,
                    'scope' => implode(' ', $oauth['scopes']),
                    'state' => $state,
                    'access_type' => 'offline',
                    'prompt' => 'consent',
                ], '', '&', PHP_QUERY_RFC3986)];
                break;

            case 'cline':
                $redirect = rtrim($callbackBase, '/') . '/' . $sessionId;
                $session += ['redirect_uri' => $redirect];
                $view = ['type' => 'redirect', 'auth_url' => $oauth['authorizeUrl'] . '?' . http_build_query([
                    'client_type' => 'extension',
                    'callback_url' => $redirect,
                    'redirect_uri' => $redirect,
                ])];
                break;

            default:
                throw new DisplayException('This provider does not use OAuth.');
        }

        Cache::put($this->key($sessionId), $session, self::SESSION_TTL);

        return ['session' => $sessionId] + $view;
    }

    /**
     * Polls a device-code login. Returns ['status' => pending|done|error, ...].
     */
    public function poll(string $sessionId): array
    {
        $session = $this->session($sessionId);
        $id = $session['provider'];
        $oauth = $this->definition($id)['oauth'];

        if ($session['flow'] === 'kilocode') {
            return $this->pollKilocode($sessionId, $session, $oauth);
        }

        $headers = ['Content-Type' => 'application/x-www-form-urlencoded', 'Accept' => 'application/json'];
        if ($id === 'kimi') {
            $headers += $this->kimiHeaders($session['extra']['device_id'] ?? null);
        }
        if ($id === 'grok-cli') {
            $headers['User-Agent'] = self::GROK_UA;
        }

        $response = $this->http()->post($oauth['tokenUrl'], [
            'headers' => $headers,
            'form_params' => [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:device_code',
                'client_id' => $oauth['clientId'],
                'device_code' => $session['device_code'],
            ],
        ]);
        $data = json_decode((string) $response->getBody(), true) ?: [];

        if (in_array($data['error'] ?? null, ['authorization_pending', 'slow_down'], true)) {
            return ['status' => 'pending'];
        }
        if (empty($data['access_token'])) {
            Cache::forget($this->key($sessionId));

            return ['status' => 'error', 'message' => $data['error_description'] ?? $data['error'] ?? ('HTTP ' . $response->getStatusCode())];
        }

        $credentials = $this->mapDeviceTokens($id, $data, $session);
        Cache::forget($this->key($sessionId));

        return ['status' => 'done', 'provider' => $this->storeAccount($id, $credentials)];
    }

    /**
     * Completes a paste-style login using the URL the browser was redirected to.
     */
    public function complete(string $sessionId, string $pasted): AiProvider
    {
        $session = $this->session($sessionId);
        $id = $session['provider'];
        $oauth = $this->definition($id)['oauth'];

        [$code, $state] = $this->parseCode($pasted);
        if (isset($session['state']) && $state !== null && !hash_equals($session['state'], $state)) {
            throw new DisplayException('The pasted URL belongs to a different login attempt. Start again.');
        }

        $credentials = match ($session['flow']) {
            'pkce_paste' => $this->exchangePkce($id, $oauth, $code, $session['verifier']),
            'google_paste' => $this->exchangeGoogle($id, $oauth, $code),
            'cline' => $this->exchangeCline($oauth, $code, $session['redirect_uri']),
            default => throw new DisplayException('This login does not use a pasted URL.'),
        };

        Cache::forget($this->key($sessionId));

        return $this->storeAccount($id, $credentials);
    }

    public function sessionProvider(string $sessionId): ?string
    {
        return Cache::get($this->key($sessionId))['provider'] ?? null;
    }

    /**
     * Returns usable credentials for a connected account, refreshing tokens when needed.
     */
    public function credentials(AiProvider $provider, bool $force = false): array
    {
        $credentials = $provider->credentials ?? [];
        $definition = $provider->definition();

        if (($definition['flow'] ?? 'apikey') === 'apikey') {
            return ['api_key' => $provider->api_key] + ($provider->settings ?? []);
        }

        $expiresAt = $provider->token_expires_at;
        $needsRefresh = $force || ($expiresAt && $expiresAt->subMinutes(5)->isPast());

        if ($provider->preset === 'github') {
            $copilotExpires = (int) ($credentials['copilot_expires_at'] ?? 0);
            if ($force || !$copilotExpires || $copilotExpires - 300 < time()) {
                if ($needsRefresh && !empty($credentials['refresh_token'])) {
                    $credentials = $this->refreshed($provider, $credentials);
                }
                $credentials = array_merge($credentials, $this->copilotToken($credentials['access_token']));
                $provider->credentials = $credentials;
                $provider->save();
            }

            return $credentials;
        }

        if ($needsRefresh && !empty($credentials['refresh_token'])) {
            $credentials = $this->refreshed($provider, $credentials);
        }

        return $credentials;
    }

    private function refreshed(AiProvider $provider, array $credentials): array
    {
        $id = $provider->preset;
        $oauth = $this->definition($id)['oauth'];
        $refresh = $credentials['refresh_token'];

        [$url, $headers, $options] = match ($id) {
            'gemini-cli', 'antigravity' => [self::GOOGLE_TOKEN_URL, [], ['form_params' => [
                'grant_type' => 'refresh_token', 'refresh_token' => $refresh,
                'client_id' => $oauth['clientId'], 'client_secret' => $oauth['clientSecret'],
            ]]],
            'codex' => [$oauth['tokenUrl'], [], ['json' => [
                'client_id' => $oauth['clientId'], 'grant_type' => 'refresh_token', 'refresh_token' => $refresh,
            ]]],
            'cline' => [$oauth['refreshUrl'], [], ['json' => [
                'refreshToken' => $refresh, 'grantType' => 'refresh_token', 'clientType' => 'extension',
            ]]],
            'kimi' => [$oauth['refreshUrl'], $this->kimiHeaders($credentials['device_id'] ?? null), ['form_params' => [
                'grant_type' => 'refresh_token', 'refresh_token' => $refresh, 'client_id' => $oauth['clientId'],
            ]]],
            'xai', 'grok-cli' => [self::XAI['tokenUrl'], [], ['form_params' => [
                'grant_type' => 'refresh_token', 'refresh_token' => $refresh, 'client_id' => $oauth['clientId'],
            ]]],
            default => [$oauth['tokenUrl'], [], ['form_params' => [
                'grant_type' => 'refresh_token', 'refresh_token' => $refresh, 'client_id' => $oauth['clientId'],
            ]]],
        };

        $response = $this->http()->post($url, $options + ['headers' => $headers + ['Accept' => 'application/json']]);
        $data = json_decode((string) $response->getBody(), true) ?: [];
        $data = $data['data'] ?? $data;
        $access = $data['access_token'] ?? $data['accessToken'] ?? null;

        if ($response->getStatusCode() >= 400 || !$access) {
            $message = 'Token refresh failed (HTTP ' . $response->getStatusCode() . '). Reconnect this account.';
            $provider->update(['last_error' => $message]);

            throw new DisplayException(($this->definition($id)['name'] ?? 'AI provider') . ': ' . $message);
        }

        $credentials['access_token'] = $access;
        $credentials['refresh_token'] = $data['refresh_token'] ?? $data['refreshToken'] ?? $refresh;
        if (!empty($data['id_token'])) {
            $credentials['id_token'] = $data['id_token'];
        }

        $provider->credentials = $credentials;
        $provider->token_expires_at = $this->expiry($data);
        $provider->last_error = null;
        $provider->save();

        return $credentials;
    }

    private function requestDeviceCode(string $id, array $oauth): array
    {
        $headers = ['Content-Type' => 'application/x-www-form-urlencoded', 'Accept' => 'application/json'];
        $form = ['client_id' => $oauth['clientId']];
        $extra = [];

        if ($id === 'github') {
            $form['scope'] = $oauth['scopes'];
        } elseif ($id === 'grok-cli') {
            $form['scope'] = $oauth['scope'];
            $form['referrer'] = $oauth['referrer'] ?? 'grok-build';
            $headers['User-Agent'] = self::GROK_UA;
        } elseif ($id === 'kimi') {
            $extra['device_id'] = (string) Str::uuid();
            $headers += $this->kimiHeaders($extra['device_id']);
        }

        $response = $this->http()->post($oauth['deviceCodeUrl'], ['headers' => $headers, 'form_params' => $form]);
        $data = $this->json($response, 'Device code request failed');

        if ($id === 'kimi' && empty($data['verification_uri_complete'])) {
            $data['verification_uri_complete'] = ($oauth['authorizeDeviceUrl'] ?? 'https://www.kimi.com/code/authorize_device') . '?user_code=' . urlencode((string) ($data['user_code'] ?? ''));
        }

        return $data + ['extra' => $extra];
    }

    private function mapDeviceTokens(string $id, array $data, array $session): array
    {
        $credentials = [
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? null,
            '_expires_in' => $data['expires_in'] ?? null,
        ];

        if ($id === 'github') {
            $user = $this->getJson('https://api.github.com/user', ['Authorization' => 'Bearer ' . $data['access_token'], 'X-GitHub-Api-Version' => '2022-11-28', 'User-Agent' => 'GitHubCopilotChat/0.26.7']);
            $credentials['account'] = $user['login'] ?? null;
            $credentials = array_merge($credentials, $this->copilotToken($data['access_token']));
        } elseif ($id === 'kimi') {
            $credentials['device_id'] = $session['extra']['device_id'] ?? null;
        } elseif ($id === 'grok-cli') {
            $credentials['account'] = $this->jwtClaim($data['id_token'] ?? null, 'email');
            $credentials['email'] = $credentials['account'];
            $user = $this->getJson('https://cli-chat-proxy.grok.com/v1/user', [
                'Authorization' => 'Bearer ' . $data['access_token'],
                'User-Agent' => self::GROK_UA,
                'x-xai-token-auth' => 'xai-grok-cli',
                'x-grok-client-version' => '0.2.93',
            ]);
            $credentials['user_id'] = $user['userId'] ?? $user['principalId'] ?? null;
            $credentials['account'] ??= $user['email'] ?? null;
        }

        return $credentials;
    }

    private function pollKilocode(string $sessionId, array $session, array $oauth): array
    {
        $response = $this->http()->get(rtrim($oauth['pollUrlBase'], '/') . '/' . $session['device_code']);
        $status = $response->getStatusCode();

        if ($status === 202) {
            return ['status' => 'pending'];
        }
        if ($status !== 200) {
            Cache::forget($this->key($sessionId));

            return ['status' => 'error', 'message' => match ($status) {
                403 => 'Authorization was denied.',
                410 => 'The code expired. Start again.',
                default => 'Poll failed: HTTP ' . $status,
            }];
        }

        $data = json_decode((string) $response->getBody(), true) ?: [];
        if (($data['status'] ?? null) !== 'approved' || empty($data['token'])) {
            return ['status' => 'pending'];
        }

        $profile = $this->getJson(rtrim($oauth['apiBaseUrl'], '/') . '/api/profile', ['Authorization' => 'Bearer ' . $data['token']]);
        Cache::forget($this->key($sessionId));

        return ['status' => 'done', 'provider' => $this->storeAccount('kilocode', [
            'access_token' => $data['token'],
            'refresh_token' => null,
            'account' => $data['userEmail'] ?? null,
            'org_id' => $profile['organizations'][0]['id'] ?? null,
        ])];
    }

    private function pkceAuthUrl(string $id, array $oauth, string $state, string $challenge): string
    {
        if ($id === 'xai') {
            return self::XAI['authorizeUrl'] . '?' . http_build_query([
                'response_type' => 'code',
                'client_id' => $oauth['clientId'],
                'redirect_uri' => self::XAI['redirectUri'],
                'scope' => self::XAI['scope'],
                'code_challenge' => $challenge,
                'code_challenge_method' => 'S256',
                'state' => $state,
                'nonce' => bin2hex(random_bytes(16)),
                'plan' => 'generic',
                'referrer' => 'cli-proxy-api',
            ], '', '&', PHP_QUERY_RFC3986);
        }

        return $oauth['authorizeUrl'] . '?' . http_build_query(array_merge([
            'response_type' => 'code',
            'client_id' => $oauth['clientId'],
            'redirect_uri' => $this->codexRedirect($oauth),
            'scope' => $oauth['scope'],
            'code_challenge' => $challenge,
            'code_challenge_method' => $oauth['codeChallengeMethod'] ?? 'S256',
        ], $oauth['extraParams'] ?? [], ['state' => $state]), '', '&', PHP_QUERY_RFC3986);
    }

    private function codexRedirect(array $oauth): string
    {
        return 'http://localhost:' . ($oauth['fixedPort'] ?? 1455) . ($oauth['callbackPath'] ?? '/auth/callback');
    }

    private function exchangePkce(string $id, array $oauth, string $code, string $verifier): array
    {
        $response = $this->http()->post($id === 'xai' ? self::XAI['tokenUrl'] : $oauth['tokenUrl'], [
            'headers' => ['Accept' => 'application/json'],
            'form_params' => [
                'grant_type' => 'authorization_code',
                'client_id' => $oauth['clientId'],
                'code' => $code,
                'redirect_uri' => $id === 'xai' ? self::XAI['redirectUri'] : $this->codexRedirect($oauth),
                'code_verifier' => $verifier,
            ],
        ]);
        $data = $this->json($response, 'Token exchange failed');

        $credentials = [
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? null,
            'id_token' => $data['id_token'] ?? null,
            '_expires_in' => $data['expires_in'] ?? null,
            'account' => $this->jwtClaim($data['id_token'] ?? null, 'email'),
        ];

        if ($id === 'codex') {
            $auth = $this->jwtClaim($data['id_token'] ?? null, 'https://api.openai.com/auth') ?: [];
            $credentials['chatgpt_account_id'] = $auth['chatgpt_account_id'] ?? $this->jwtClaim($data['id_token'] ?? null, 'account_id');
            $credentials['plan'] = $auth['chatgpt_plan_type'] ?? null;
        }

        return $credentials;
    }

    private function exchangeGoogle(string $id, array $oauth, string $code): array
    {
        $response = $this->http()->post($oauth['tokenUrl'], [
            'headers' => ['Accept' => 'application/json'],
            'form_params' => [
                'grant_type' => 'authorization_code',
                'client_id' => $oauth['clientId'],
                'client_secret' => $oauth['clientSecret'],
                'code' => $code,
                'redirect_uri' => self::GOOGLE_REDIRECT,
            ],
        ]);
        $data = $this->json($response, 'Google token exchange failed');
        $token = $data['access_token'];

        $user = $this->getJson($oauth['userInfoUrl'] . '?alt=json', ['Authorization' => 'Bearer ' . $token]);
        $metadata = ['ideType' => 9, 'platform' => 3, 'pluginType' => 2];
        $headers = ['Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json'];
        if ($id === 'antigravity') {
            $headers += ['User-Agent' => $oauth['loadCodeAssistUserAgent'], 'x-request-source' => 'local'];
        }

        $load = $this->http()->post($oauth['loadCodeAssistEndpoint'] ?? 'https://cloudcode-pa.googleapis.com/v1internal:loadCodeAssist', [
            'headers' => $headers,
            'json' => $id === 'antigravity' ? ['metadata' => $metadata] : ['metadata' => $metadata, 'mode' => 1],
        ]);
        $loaded = json_decode((string) $load->getBody(), true) ?: [];
        $project = $loaded['cloudaicompanionProject']['id'] ?? $loaded['cloudaicompanionProject'] ?? '';

        if ($id === 'antigravity' && $project) {
            $tier = collect($loaded['allowedTiers'] ?? [])->firstWhere('isDefault', true)['id'] ?? 'legacy-tier';
            $this->http(15)->post($oauth['onboardUserEndpoint'], ['headers' => $headers, 'json' => ['tierId' => $tier, 'metadata' => $metadata]]);
        }

        return [
            'access_token' => $token,
            'refresh_token' => $data['refresh_token'] ?? null,
            '_expires_in' => $data['expires_in'] ?? null,
            'account' => $user['email'] ?? null,
            'project_id' => is_string($project) ? $project : '',
        ];
    }

    private function exchangeCline(array $oauth, string $code, string $redirect): array
    {
        $padded = $code . str_repeat('=', (4 - strlen($code) % 4) % 4);
        $decoded = base64_decode(strtr($padded, '-_', '+/'), true);
        if ($decoded !== false && ($brace = strrpos($decoded, '}')) !== false) {
            $token = json_decode(substr($decoded, 0, $brace + 1), true);
            if (!empty($token['accessToken'])) {
                return [
                    'access_token' => $token['accessToken'],
                    'refresh_token' => $token['refreshToken'] ?? null,
                    'account' => $token['email'] ?? null,
                    '_expires_at' => $token['expiresAt'] ?? null,
                ];
            }
        }

        $response = $this->http()->post($oauth['tokenExchangeUrl'], ['json' => [
            'grant_type' => 'authorization_code', 'code' => $code, 'client_type' => 'extension', 'redirect_uri' => $redirect,
        ]]);
        $data = $this->json($response, 'Cline token exchange failed');
        $data = $data['data'] ?? $data;

        return [
            'access_token' => $data['accessToken'],
            'refresh_token' => $data['refreshToken'] ?? null,
            'account' => $data['userInfo']['email'] ?? null,
            '_expires_at' => $data['expiresAt'] ?? null,
        ];
    }

    private function copilotToken(string $githubToken): array
    {
        $data = $this->getJson('https://api.github.com/copilot_internal/v2/token', [
            'Authorization' => 'token ' . $githubToken,
            'User-Agent' => 'GitHubCopilotChat/0.38.0',
            'Editor-Version' => 'vscode/1.110.0',
            'Editor-Plugin-Version' => 'copilot-chat/0.38.0',
            'x-github-api-version' => '2025-04-01',
        ]);

        if (empty($data['token'])) {
            throw new DisplayException('This GitHub account does not have an active Copilot subscription (no Copilot token was issued).');
        }

        return ['copilot_token' => $data['token'], 'copilot_expires_at' => (int) ($data['expires_at'] ?? time() + 1500)];
    }

    private function storeAccount(string $id, array $credentials): AiProvider
    {
        $definition = $this->definition($id);
        $account = $credentials['account'] ?? null;
        $expiry = $this->expiry([
            'expires_in' => $credentials['_expires_in'] ?? null,
            'expiresAt' => $credentials['_expires_at'] ?? null,
        ]);
        unset($credentials['_expires_in'], $credentials['_expires_at']);

        $provider = AiProvider::query()->firstOrNew(['preset' => $id, 'account' => $account ?: null]);
        $provider->fill([
            'name' => $definition['name'] . ($account ? ' (' . $account . ')' : ''),
            'base_url' => $definition['base_url'],
            'enabled' => true,
            'priority' => $provider->exists ? $provider->priority : ($definition['group'] === 'subscription' ? 10 : 50),
            'last_error' => null,
        ]);
        $provider->credentials = $credentials;
        $provider->token_expires_at = $expiry;
        $provider->save();

        return $provider;
    }

    private function expiry(array $data): ?\Illuminate\Support\Carbon
    {
        if (!empty($data['expires_in'])) {
            return now()->addSeconds((int) $data['expires_in']);
        }
        if (!empty($data['expiresAt'])) {
            return \Illuminate\Support\Carbon::parse($data['expiresAt']);
        }

        return null;
    }

    private function parseCode(string $pasted): array
    {
        $pasted = trim($pasted);
        if (str_contains($pasted, 'code=')) {
            $query = parse_url($pasted, PHP_URL_QUERY) ?: (str_starts_with($pasted, '?') ? substr($pasted, 1) : $pasted);
            parse_str($query, $params);
            if (!empty($params['error'])) {
                throw new DisplayException('Login was cancelled or failed: ' . $params['error']);
            }
            if (!empty($params['code'])) {
                return [(string) $params['code'], isset($params['state']) ? (string) $params['state'] : null];
            }
        }

        if ($pasted === '' || str_contains($pasted, ' ')) {
            throw new DisplayException('Paste the full URL from the browser address bar after logging in.');
        }

        return [$pasted, null];
    }

    private function kimiHeaders(?string $deviceId): array
    {
        return [
            'X-Msh-Platform' => '9router',
            'X-Msh-Version' => '1.0.0',
            'X-Msh-Device-Name' => gethostname() ?: 'unknown',
            'X-Msh-Device-Model' => 'Linux x64',
            'X-Msh-Device-Id' => $deviceId ?: ('kimi-' . time()),
        ];
    }

    public function kimiRequestHeaders(?string $deviceId): array
    {
        return $this->kimiHeaders($deviceId);
    }

    private function jwtClaim(?string $jwt, string $claim): mixed
    {
        if (!$jwt || substr_count($jwt, '.') !== 2) {
            return null;
        }
        $payload = json_decode(base64_decode(strtr(explode('.', $jwt)[1], '-_', '+/')), true);

        return is_array($payload) ? ($payload[$claim] ?? null) : null;
    }

    private function getJson(string $url, array $headers): array
    {
        try {
            $response = $this->http()->get($url, ['headers' => $headers + ['Accept' => 'application/json']]);
        } catch (\Throwable) {
            return [];
        }

        return $response->getStatusCode() < 400 ? (json_decode((string) $response->getBody(), true) ?: []) : [];
    }

    private function json($response, string $error): array
    {
        $data = json_decode((string) $response->getBody(), true);
        if ($response->getStatusCode() >= 400 || !is_array($data)) {
            throw new DisplayException($error . ' (HTTP ' . $response->getStatusCode() . '): ' . mb_substr((string) $response->getBody(), 0, 300));
        }

        return $data;
    }

    private function session(string $sessionId): array
    {
        $session = Cache::get($this->key($sessionId));
        if (!is_array($session)) {
            throw new DisplayException('This login session expired. Start again.');
        }

        return $session;
    }

    private function key(string $sessionId): string
    {
        return 'ai-oauth:' . $sessionId;
    }

    private function base64Url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    private function pkceChallenge(string $verifier): string
    {
        return $this->base64Url(hash('sha256', $verifier, true));
    }
}
