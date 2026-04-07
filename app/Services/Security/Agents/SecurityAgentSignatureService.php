<?php

namespace Pterodactyl\Services\Security\Agents;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Pterodactyl\Models\Security\SecurityAgent;
use Pterodactyl\Services\Security\SecurityCenterSettingsService;
use Pterodactyl\Services\Security\SecurityOrchestratorService;
use Pterodactyl\Services\Security\SecurityVocabulary;

class SecurityAgentSignatureService
{
    public function __construct(
        private SecurityAgentService $agents,
        private SecurityCenterSettingsService $settings,
        private SecurityOrchestratorService $orchestrator,
    ) {
    }

    public function authenticate(Request $request): SecurityAgent
    {
        $agentId = trim((string) ($request->input('agent_id', $request->header('X-Security-Agent-Id', ''))));
        $agent = $this->agents->resolve($agentId);
        if (!$agent) {
            $this->recordFailure($request, null, 'unknown_agent', 'Security agent identifier was not recognised.');

            throw new AccessDeniedHttpException('Security agent identifier is invalid.');
        }

        $timestamp = (int) $request->header('X-Security-Timestamp', 0);
        $nonce = trim((string) $request->header('X-Security-Nonce', ''));
        $signature = trim((string) $request->header('X-Security-Signature', ''));

        if ($timestamp <= 0 || $nonce === '' || $signature === '') {
            $this->recordFailure($request, $agent, 'missing_headers', 'Security agent signing headers were missing.');

            throw new AccessDeniedHttpException('Security agent headers are incomplete.');
        }

        $skew = $this->settings->config()['agent']['clock_skew_seconds'];
        if (abs(now()->timestamp - $timestamp) > $skew) {
            $this->recordFailure($request, $agent, 'clock_skew', 'Security agent timestamp exceeded the allowed clock skew.');

            throw new AccessDeniedHttpException('Security agent timestamp is outside the allowed skew window.');
        }

        $nonceKey = sprintf('security:center:agent-nonce:%s:%s', $agent->uuid, sha1($nonce));
        if (!$this->cache()->add($nonceKey, true, now()->addSeconds($this->settings->config()['agent']['nonce_ttl_seconds']))) {
            $this->recordFailure($request, $agent, 'replay', 'Security agent nonce replay detected.');

            throw new AccessDeniedHttpException('Security agent nonce replay detected.');
        }

        $body = (string) $request->getContent();
        $base = $timestamp . "\n" . $nonce . "\n" . $body;

        foreach ($this->agents->activeSecrets($agent) as $secret) {
            $expected = hash_hmac('sha256', $base, $secret);
            if (hash_equals($expected, $signature)) {
                return $agent;
            }
        }

        $this->recordFailure($request, $agent, 'signature_mismatch', 'Security agent HMAC signature validation failed.');

        throw new AccessDeniedHttpException('Security agent signature validation failed.');
    }

    private function recordFailure(Request $request, ?SecurityAgent $agent, string $reason, string $summary): void
    {
        $this->orchestrator->record('agent_signature_failure', [
            'severity' => 'high',
            'confidence' => 95,
            'source_ip' => $request->ip(),
            'node_id' => $agent?->node_id,
            'target' => $agent,
            'summary' => $summary,
            'evidence' => [
                'reason' => $reason,
                'agent_id' => $agent?->id,
                'agent_uuid' => $agent?->uuid,
                'path' => $request->path(),
            ],
            'blocked' => true,
            'verdict' => SecurityVocabulary::VERDICT_BLOCKED,
            'mitigation_stage' => SecurityVocabulary::STAGE_TEMP_BLOCK,
        ]);
    }

    private function cache(): CacheRepository
    {
        $cacheStore = config('security.cache_store');

        return $cacheStore ? Cache::store($cacheStore) : Cache::store();
    }
}
