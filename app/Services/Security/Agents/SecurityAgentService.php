<?php

namespace Pterodactyl\Services\Security\Agents;

use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Pterodactyl\Models\Security\SecurityAction;
use Pterodactyl\Models\Security\SecurityAgent;
use Pterodactyl\Models\Security\SecurityEvent;
use Pterodactyl\Models\Node;
use Pterodactyl\Services\Security\SecurityCenterSettingsService;
use Pterodactyl\Services\Security\SecurityOrchestratorService;
use Pterodactyl\Services\Security\SecurityVocabulary;

class SecurityAgentService
{
    private const DEFAULT_CAPABILITIES = [
        'connection_snapshot',
        'process_drift',
        'integrity_check',
        'route_flood',
        'origin_exhaustion',
    ];

    public function __construct(
        private Encrypter $encrypter,
        private SecurityOrchestratorService $orchestrator,
        private SecurityCenterSettingsService $settings,
    ) {
    }

    public function create(string $name, ?Node $node = null, array $capabilities = []): array
    {
        $secret = $this->newSecret();

        $agent = SecurityAgent::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'node_id' => $node?->id,
            'status' => SecurityVocabulary::AGENT_PROVISIONING,
            'capabilities' => $this->normalizeCapabilities($capabilities),
            'current_secret_encrypted' => $this->encrypter->encrypt($secret),
        ]);

        return [$agent, $secret];
    }

    public function suggestedName(?Node $node = null): string
    {
        if (!$node) {
            return 'Security Agent';
        }

        return sprintf('%s Security Agent', trim($node->name) !== '' ? $node->name : ('Node #' . $node->id));
    }

    public function defaultCapabilities(): array
    {
        return self::DEFAULT_CAPABILITIES;
    }

    public function missingNodes(): Collection
    {
        $linkedNodeIds = SecurityAgent::query()
            ->whereNotNull('node_id')
            ->pluck('node_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        return Node::query()
            ->when($linkedNodeIds !== [], fn ($query) => $query->whereNotIn('id', $linkedNodeIds))
            ->orderBy('name')
            ->get();
    }

    public function provisionMissingNodes(?Collection $nodes = null, array $capabilities = []): array
    {
        $capabilities = $this->normalizeCapabilities($capabilities);
        $nodes = $nodes ?? $this->missingNodes();
        $created = [];

        foreach ($nodes as $node) {
            if (SecurityAgent::query()->where('node_id', $node->id)->exists()) {
                continue;
            }

            [$agent, $secret] = $this->create($this->suggestedName($node), $node, $capabilities);

            $created[] = [
                'id' => $agent->id,
                'uuid' => $agent->uuid,
                'name' => $agent->name,
                'node_id' => $node->id,
                'node_name' => $node->name,
                'secret' => $secret,
            ];
        }

        return $created;
    }

    public function rotateSecret(SecurityAgent $agent): string
    {
        $secret = $this->newSecret();

        $agent->forceFill([
            'previous_secret_encrypted' => $agent->current_secret_encrypted,
            'current_secret_encrypted' => $this->encrypter->encrypt($secret),
            'secret_rotated_at' => now(),
        ])->saveOrFail();

        return $secret;
    }

    public function resolve(string $agentId): ?SecurityAgent
    {
        $agentId = trim($agentId);
        if ($agentId === '') {
            return null;
        }

        return SecurityAgent::query()
            ->where('uuid', $agentId)
            ->orWhere('id', is_numeric($agentId) ? (int) $agentId : 0)
            ->first();
    }

    public function activeSecrets(SecurityAgent $agent): array
    {
        $secrets = [];

        foreach ([$agent->current_secret_encrypted, $agent->previous_secret_encrypted] as $index => $encrypted) {
            if (!is_string($encrypted) || trim($encrypted) === '') {
                continue;
            }

            if ($index === 1) {
                $rotatedAt = $agent->secret_rotated_at;
                if (!$rotatedAt || $rotatedAt->lt(now()->subSeconds($this->settings->config()['agent']['secret_rotation_grace_seconds']))) {
                    continue;
                }
            }

            try {
                $secrets[] = $this->encrypter->decrypt($encrypted);
            } catch (\Throwable) {
            }
        }

        return $secrets;
    }

    public function recordHeartbeat(SecurityAgent $agent, array $payload, Request $request): SecurityAgent
    {
        $nodeId = Arr::get($payload, 'node_id');
        if ($agent->node_id && $nodeId && (int) $agent->node_id !== (int) $nodeId) {
            $this->orchestrator->record('agent_signature_failure', [
                'severity' => 'high',
                'confidence' => 90,
                'source_ip' => $request->ip(),
                'node_id' => $agent->node_id,
                'target' => $agent,
                'summary' => 'Agent reported a different node identifier than the one assigned in Security Center.',
                'evidence' => [
                    'expected_node_id' => $agent->node_id,
                    'reported_node_id' => $nodeId,
                ],
                'blocked' => true,
                'verdict' => SecurityVocabulary::VERDICT_BLOCKED,
                'mitigation_stage' => SecurityVocabulary::STAGE_CONTAIN,
            ]);
        }

        $agent->forceFill([
            'node_id' => $agent->node_id ?: ($nodeId ?: null),
            'status' => $agent->status === SecurityVocabulary::AGENT_DISABLED ? SecurityVocabulary::AGENT_DISABLED : SecurityVocabulary::AGENT_ACTIVE,
            'capabilities' => array_values(array_filter(array_map('strval', Arr::wrap($payload['capabilities'] ?? $agent->capabilities ?? [])))),
            'last_heartbeat_at' => now(),
            'last_ip' => $request->ip(),
            'meta' => array_merge($agent->meta ?? [], [
                'version' => $payload['version'] ?? null,
                'runtime' => $payload['meta'] ?? [],
                'last_status' => $payload['status'] ?? null,
            ]),
        ])->saveOrFail();

        return $agent->fresh();
    }

    public function recordReports(SecurityAgent $agent, array $reports, Request $request): array
    {
        $events = [];
        foreach ($reports as $report) {
            $ruleKey = (string) ($report['rule_key'] ?? 'agent_signature_failure');
            $verdict = $report['verdict'] ?? null;
            $mitigationStage = $report['mitigation_stage'] ?? null;

            if ($ruleKey === 'origin_exhaustion' && !$verdict) {
                $verdict = SecurityVocabulary::VERDICT_NOT_CONTROLLABLE;
                $mitigationStage = SecurityVocabulary::STAGE_OBSERVE;
            }

            $events[] = $this->orchestrator->record($ruleKey, [
                'class' => $report['class'] ?? null,
                'surface' => $report['surface'] ?? 'agent',
                'severity' => $report['severity'] ?? 'medium',
                'confidence' => $report['confidence'] ?? 70,
                'source_ip' => $report['source_ip'] ?? $request->ip(),
                'fingerprint' => $report['fingerprint'] ?? null,
                'actor_type' => $report['actor_type'] ?? null,
                'actor_id' => $report['actor_id'] ?? null,
                'node_id' => $report['node_id'] ?? $agent->node_id,
                'target_type' => $report['target_type'] ?? null,
                'target_id' => $report['target_id'] ?? null,
                'summary' => $report['summary'] ?? null,
                'evidence' => array_merge($report['evidence'] ?? [], [
                    'agent_uuid' => $agent->uuid,
                    'agent_name' => $agent->name,
                ]),
                'blocked' => (bool) ($report['blocked'] ?? false),
                'verdict' => $verdict,
                'mitigation_stage' => $mitigationStage,
                'execute_actions' => !array_key_exists('execute_actions', $report) || (bool) $report['execute_actions'],
                'agent' => $agent,
                'capabilities' => Arr::wrap($agent->capabilities),
            ]);
        }

        $agent->forceFill([
            'last_reported_at' => now(),
            'last_ip' => $request->ip(),
        ])->saveOrFail();

        return $events;
    }

    public function pullActions(SecurityAgent $agent, int $limit = 25): Collection
    {
        $actions = SecurityAction::query()
            ->where('agent_id', $agent->id)
            ->where('status', SecurityVocabulary::ACTION_PENDING)
            ->where(function ($query) {
                $query->whereNull('execute_after')
                    ->orWhere('execute_after', '<=', now());
            })
            ->orderBy('id')
            ->limit(max(1, min($limit, 50)))
            ->get();

        foreach ($actions as $action) {
            $action->forceFill([
                'status' => SecurityVocabulary::ACTION_DISPATCHED,
                'acknowledged_at' => now(),
            ])->saveOrFail();
        }

        return $actions;
    }

    public function completeAction(SecurityAgent $agent, int $actionId, array $payload): ?SecurityAction
    {
        $action = SecurityAction::query()
            ->where('agent_id', $agent->id)
            ->where('id', $actionId)
            ->first();

        if (!$action) {
            return null;
        }

        $action->forceFill([
            'status' => !empty($payload['success']) ? SecurityVocabulary::ACTION_COMPLETED : SecurityVocabulary::ACTION_FAILED,
            'result' => [
                'success' => (bool) ($payload['success'] ?? false),
                'artifact_refs' => Arr::wrap($payload['artifact_refs'] ?? []),
                'stdout_summary' => $payload['stdout_summary'] ?? null,
                'stderr_summary' => $payload['stderr_summary'] ?? null,
                'verdict_impact' => $payload['verdict_impact'] ?? null,
                'meta' => $payload['meta'] ?? null,
            ],
            'completed_at' => now(),
        ])->saveOrFail();

        return $action->fresh();
    }

    public function markStaleAgents(): int
    {
        if (!(bool) config('security.agent.enabled', true)) {
            return 0;
        }

        $ttl = $this->settings->config()['agent']['heartbeat_ttl_seconds'];
        $staleBefore = now()->subSeconds($ttl);
        $count = 0;

        SecurityAgent::query()
            ->whereIn('status', [SecurityVocabulary::AGENT_ACTIVE, SecurityVocabulary::AGENT_PROVISIONING])
            ->where(function ($query) use ($staleBefore) {
                $query->whereNull('last_heartbeat_at')
                    ->orWhere('last_heartbeat_at', '<', $staleBefore);
            })
            ->get()
            ->each(function (SecurityAgent $agent) use (&$count, $staleBefore) {
                $agent->forceFill([
                    'status' => SecurityVocabulary::AGENT_STALE,
                ])->saveOrFail();

                $this->orchestrator->record('agent_silence', [
                    'severity' => 'high',
                    'confidence' => 92,
                    'source_ip' => $agent->last_ip,
                    'node_id' => $agent->node_id,
                    'target' => $agent,
                    'summary' => sprintf(
                        'Security agent missed its heartbeat deadline. Last heartbeat must be newer than %s.',
                        $staleBefore->toIso8601String()
                    ),
                    'evidence' => [
                        'agent_uuid' => $agent->uuid,
                        'agent_name' => $agent->name,
                        'last_heartbeat_at' => optional($agent->last_heartbeat_at)?->toIso8601String(),
                        'last_reported_at' => optional($agent->last_reported_at)?->toIso8601String(),
                    ],
                    'blocked' => false,
                    'verdict' => SecurityVocabulary::VERDICT_CONTAINED,
                    'mitigation_stage' => SecurityVocabulary::STAGE_CONTAIN,
                    'execute_actions' => (bool) config('security.agent.enabled', true),
                    'agent' => $agent,
                    'capabilities' => Arr::wrap($agent->capabilities),
                ]);

                ++$count;
            });

        return $count;
    }

    private function newSecret(): string
    {
        return Str::random(64);
    }

    private function normalizeCapabilities(array $capabilities = []): array
    {
        $normalized = array_values(array_unique(array_filter(array_map(
            fn ($value) => trim((string) $value),
            Arr::wrap($capabilities)
        ))));

        return $normalized !== [] ? $normalized : self::DEFAULT_CAPABILITIES;
    }
}
