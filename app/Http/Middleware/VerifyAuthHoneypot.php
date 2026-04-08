<?php

namespace Pterodactyl\Http\Middleware;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Pterodactyl\Services\Security\AuthSecurityService;

class VerifyAuthHoneypot
{
    public function __construct(private AuthSecurityService $security)
    {
    }

    public function handle(Request $request, \Closure $next): mixed
    {
        if ($this->security->isTrustedRequest($request)) {
            return $next($request);
        }

        $identifier = $this->security->getIdentifierFromRequest($request);
        $triggered = false;

        $honeypotFields = config('security.honeypot.fields', ['website', 'company']);
        foreach ($honeypotFields as $field) {
            if (trim((string) $request->input($field, '')) !== '') {
                $triggered = true;
                break;
            }
        }

        $timingField = (string) config('security.honeypot.timing_field', 'form_rendered_at');
        $minFillSeconds = max(1, (int) config('security.honeypot.min_fill_seconds', 2));
        $submittedAt = (int) $request->input($timingField, 0);

        if ($submittedAt > 0) {
            $nowMs = (int) floor(microtime(true) * 1000);
            if (($nowMs - $submittedAt) < ($minFillSeconds * 1000)) {
                $triggered = true;
            }
        }

        if (!$triggered) {
            return $next($request);
        }

        $this->security->registerFailure($request, 10, 'honeypot_triggered', $identifier);
        $this->sleepTarpit();

        return response()->json([
            'errors' => [
                [
                    'code' => 'SecurityChallengeRequired',
                    'status' => (string) Response::HTTP_TOO_MANY_REQUESTS,
                    'detail' => 'Unable to process this request right now. Please retry shortly.',
                ],
            ],
            'challenge_required' => true,
            'next_action' => 'retry',
        ], Response::HTTP_TOO_MANY_REQUESTS);
    }

    private function sleepTarpit(): void
    {
        $minDelay = max(100, (int) config('security.honeypot.delay_ms_min', 350));
        $maxDelay = max($minDelay, (int) config('security.honeypot.delay_ms_max', 900));
        usleep(random_int($minDelay * 1000, $maxDelay * 1000));
    }
}
