<?php

namespace Pterodactyl\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Services\Security\SecurityOrchestratorService;
use Pterodactyl\Services\Security\SecurityRuntimePolicyService;
use Pterodactyl\Services\Security\SecurityVocabulary;

class EnforceSecurityPolicies
{
    public function __construct(
        private SecurityRuntimePolicyService $runtime,
        private SecurityOrchestratorService $orchestrator,
    ) {
    }

    public function handle(Request $request, Closure $next): mixed
    {
        if (!(bool) config('security.enabled', true)) {
            return $next($request);
        }

        if ($this->runtime->matchesTrustedNetwork($request->ip())) {
            return $next($request);
        }

        if ($reason = $this->runtime->breakGlassReason($request)) {
            if ($this->runtime->shouldLogBreakGlass($request, $reason)) {
                Activity::event('security:break-glass-used')
                    ->withRequestMetadata()
                    ->property([
                        'reason' => $reason,
                        'path' => $request->path(),
                    ])
                    ->log('Security break-glass bypass used.');
            }

            return $next($request);
        }

        $match = $this->runtime->match($request);
        if (!$match) {
            return $next($request);
        }

        $scopeKey = (string) ($request->ip() . '|' . ($match['scope'] ?? 'unknown') . '|' . ($request->route()?->getName() ?: $request->path()));
        if ($this->runtime->shouldLogPolicyEvent((string) ($match['reason'] ?? 'policy_block'), $scopeKey)) {
            $this->orchestrator->record('runtime_policy_block', [
                'severity' => 'medium',
                'confidence' => 90,
                'source_ip' => $request->ip(),
                'fingerprint' => $this->runtime->fingerprintFromRequest($request),
                'summary' => (string) ($match['message'] ?? 'Request denied by Security Center runtime policy.'),
                'evidence' => [
                    'reason' => $match['reason'] ?? 'unknown',
                    'scope' => $match['scope'] ?? 'unknown',
                    'route' => $request->route()?->getName() ?: $request->path(),
                    'policy' => $match['payload'] ?? [],
                ],
                'blocked' => true,
                'verdict' => SecurityVocabulary::VERDICT_BLOCKED,
                'mitigation_stage' => SecurityVocabulary::STAGE_TEMP_BLOCK,
                'execute_actions' => false,
            ]);
        }

        $message = (string) ($match['message'] ?? 'Request denied by Security Center.');
        $retryAfter = data_get($match, 'payload.expires_at');

        if ($request->expectsJson()) {
            $headers = [];
            if (is_string($retryAfter) && $retryAfter !== '') {
                $headers['Retry-After'] = (string) max(0, (strtotime($retryAfter) ?: 0) - time());
            }

            return response()->json([
                'errors' => [
                    [
                        'code' => 'SecurityPolicyBlocked',
                        'status' => '423',
                        'detail' => $message,
                    ],
                ],
                'verdict' => SecurityVocabulary::VERDICT_BLOCKED,
            ], 423, $headers);
        }

        abort(423, $message);
    }
}
