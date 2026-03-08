<?php

namespace Pterodactyl\Http\Middleware;

use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Pterodactyl\Events\Auth\FailedCaptcha;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use Pterodactyl\Services\Security\AuthSecurityService;

class VerifyReCaptcha
{
    /**
     * VerifyReCaptcha constructor.
     */
    public function __construct(
        private Dispatcher $dispatcher,
        private Repository $config,
        private AuthSecurityService $security,
    ) {
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, \Closure $next): mixed
    {
        $identifier = $this->security->getIdentifierFromRequest($request);
        $state = $this->security->evaluate($request, $identifier);

        if ($state['locked']) {
            return $this->challengeResponse($state['retry_after'], 'Authentication is temporarily restricted. Please try again shortly.');
        }

        $captchaEnabled = (bool) $this->config->get('turnstile.enabled');
        if (!$captchaEnabled) {
            return $next($request);
        }

        $token = $this->resolveToken($request);
        if ($token === null) {
            if (!$this->isTokenRequired($request, $state)) {
                return $next($request);
            }

            $this->security->registerFailure($request, 4, 'captcha_missing', $identifier);

            return $this->challengeResponse($state['retry_after'], 'Complete Cloudflare Turnstile verification before continuing.');
        }

        if ($this->isTokenValid($token, $request)) {
            return $next($request);
        }

        $this->security->registerFailure($request, 4, 'captcha_failed', $identifier);

        $this->dispatcher->dispatch(new FailedCaptcha($request->ip(), null));

        return $this->challengeResponse($state['retry_after']);
    }

    private function challengeResponse(int $retryAfter, string $message = 'Additional verification is required before continuing.'): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'errors' => [
                [
                    'code' => 'SecurityChallengeRequired',
                    'status' => (string) Response::HTTP_TOO_MANY_REQUESTS,
                    'detail' => $message,
                ],
            ],
            'challenge_required' => true,
            'retry_after' => $retryAfter,
            'next_action' => 'retry',
        ], Response::HTTP_TOO_MANY_REQUESTS, $retryAfter > 0 ? ['Retry-After' => (string) $retryAfter] : []);
    }

    private function resolveToken(Request $request): ?string
    {
        $token = trim((string) $request->input('cf-turnstile-response', ''));

        return $token !== '' ? $token : null;
    }

    private function isTokenValid(string $token, Request $request): bool
    {
        return $this->verifyTurnstileToken($token, $request);
    }

    private function isTokenRequired(Request $request, array $state): bool
    {
        return $request->routeIs('auth.post.login') || (bool) ($state['challenge_required'] ?? false);
    }

    private function verifyTurnstileToken(string $token, Request $request): bool
    {
        $secret = trim((string) $this->config->get('turnstile.secret_key'));
        if ($secret === '') {
            return false;
        }

        try {
            $client = new Client();
            $response = $client->post((string) $this->config->get('turnstile.domain'), [
                'form_params' => [
                    'secret' => $secret,
                    'response' => $token,
                    'remoteip' => $request->ip(),
                ],
            ]);
        } catch (\Throwable) {
            return false;
        }

        if ($response->getStatusCode() !== 200) {
            return false;
        }

        $result = json_decode($response->getBody());
        if (empty($result) || !($result->success ?? false)) {
            return false;
        }

        if (!(bool) $this->config->get('turnstile.verify_domain', true)) {
            return true;
        }

        return $this->isHostnameVerified($result, $request);
    }

    private function isHostnameVerified(\stdClass $result, Request $request): bool
    {
        $url = parse_url($request->url());

        return !empty($result->hostname) && $result->hostname === array_get($url, 'host');
    }
}
