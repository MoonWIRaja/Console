<?php

namespace Pterodactyl\Http\Middleware\Api;

use IPTools\IP;
use IPTools\Range;
use Illuminate\Http\Request;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Models\ApiKey;
use Laravel\Sanctum\TransientToken;
use Pterodactyl\Services\Security\SecurityOrchestratorService;
use Pterodactyl\Services\Security\SecurityVocabulary;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class AuthenticateIPAccess
{
    public function __construct(private SecurityOrchestratorService $orchestrator)
    {
    }

    /**
     * Determine if a request IP has permission to access the API.
     *
     * @throws \Exception
     * @throws AccessDeniedHttpException
     */
    public function handle(Request $request, \Closure $next): mixed
    {
        /** @var TransientToken|\Pterodactyl\Models\ApiKey $token */
        $token = $request->user()->currentAccessToken();

        // If this is a stateful request just push the request through to the next
        // middleware in the stack, there is nothing we need to explicitly check. If
        // this is a valid API Key, but there is no allowed IP restriction, also pass
        // the request through.
        if ($token instanceof TransientToken || empty($token->allowed_ips)) {
            return $next($request);
        }

        $find = new IP($request->ip());
        foreach ($token->allowed_ips as $ip) {
            if (Range::parse($ip)->contains($find)) {
                return $next($request);
            }
        }

        Activity::event('auth:ip-blocked')
            ->actor($request->user())
            ->subject($request->user(), $token)
            ->property('identifier', $token->identifier)
            ->log();

        $this->orchestrator->record('api_ip_anomaly', [
            'severity' => 'high',
            'confidence' => 95,
            'source_ip' => $request->ip(),
            'actor' => $request->user(),
            'target' => $token instanceof ApiKey ? $token : null,
            'api_key' => $token instanceof ApiKey ? $token : null,
            'summary' => 'API token was used from an IP outside the configured allowlist.',
            'evidence' => [
                'identifier' => $token->identifier,
                'allowed_ips' => $token->allowed_ips,
                'route' => $request->route()?->getName() ?: $request->path(),
            ],
            'blocked' => true,
            'verdict' => SecurityVocabulary::VERDICT_BLOCKED,
            'mitigation_stage' => SecurityVocabulary::STAGE_TEMP_BLOCK,
        ]);

        throw new AccessDeniedHttpException('This IP address (' . $request->ip() . ') does not have permission to access the API using these credentials.');
    }
}
