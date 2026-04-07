<?php

namespace Pterodactyl\Services\Security;

use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Support\Arr;
use Pterodactyl\Models\ApiKey;
use Pterodactyl\Models\Security\SecurityAction;
use Pterodactyl\Models\Security\SecurityAgent;
use Pterodactyl\Models\Security\SecurityEvent;
use Pterodactyl\Models\Security\SecurityIncident;
use Pterodactyl\Models\Security\SecurityRule;
use Pterodactyl\Models\Session;
use Pterodactyl\Models\User;

class SecurityActionExecutorService
{
    public function __construct(
        private SecurityRuntimePolicyService $runtime,
        private SecurityCenterSettingsService $settings,
        private SecurityQuarantineService $quarantine,
    ) {
    }

    public function execute(SecurityRule $rule, SecurityIncident $incident, SecurityEvent $event, array $context = []): array
    {
        $created = [];
        $policies = array_values(array_unique(array_map(
            'strval',
            array_diff(
                Arr::wrap($rule->response_policy),
                Arr::wrap($context['disable_policies'] ?? [])
            )
        )));

        foreach ($policies as $policy) {
            $created[] = match ($policy) {
                'challenge' => $this->recordCompletedAction($incident, $event, $policy, $context, ['message' => 'Challenge escalation recorded.']),
                'rate_limit' => $this->holdRoute($incident, $event, $context),
                'temp_block', 'block_ip' => $this->blockIp($incident, $event, $context),
                'block_fingerprint' => $this->blockFingerprint($incident, $event, $context),
                'terminate_sessions' => $this->terminateSessions($incident, $event, $context),
                'revoke_api_token' => $this->revokeApiToken($incident, $event, $context),
                'hold_route' => $this->holdRoute($incident, $event, $context),
                'contain' => $this->containAgent($incident, $event, $context),
                'quarantine_artifact' => $this->quarantineArtifact($incident, $event, $context),
                default => $this->recordFailedAction($incident, $event, $policy, $context, 'Unknown response policy.'),
            };
        }

        return $created;
    }

    private function blockIp(SecurityIncident $incident, SecurityEvent $event, array $context): SecurityAction
    {
        $ip = trim((string) ($context['source_ip'] ?? $event->source_ip ?? ''));
        if ($ip === '') {
            return $this->recordFailedAction($incident, $event, 'block_ip', $context, 'No source IP was available.');
        }

        $this->runtime->denyIp($ip, null, [
            'incident_id' => $incident->id,
            'reason' => $incident->title,
        ]);

        return $this->recordCompletedAction($incident, $event, 'block_ip', $context, [
            'ip' => $ip,
            'message' => 'IP denylist applied.',
        ], $ip);
    }

    private function blockFingerprint(SecurityIncident $incident, SecurityEvent $event, array $context): SecurityAction
    {
        $fingerprint = trim((string) ($context['fingerprint'] ?? $event->fingerprint ?? ''));
        if ($fingerprint === '') {
            return $this->recordFailedAction($incident, $event, 'block_fingerprint', $context, 'No request fingerprint was available.');
        }

        $this->runtime->denyFingerprint($fingerprint, null, [
            'incident_id' => $incident->id,
            'reason' => $incident->title,
        ]);

        return $this->recordCompletedAction($incident, $event, 'block_fingerprint', $context, [
            'fingerprint' => $fingerprint,
            'message' => 'Request fingerprint denylist applied.',
        ], null, $fingerprint);
    }

    private function terminateSessions(SecurityIncident $incident, SecurityEvent $event, array $context): SecurityAction
    {
        $user = $this->resolveUser($context);
        if (!$user) {
            return $this->recordFailedAction($incident, $event, 'terminate_sessions', $context, 'No user context was available.');
        }

        $count = Session::query()->where('user_id', $user->id)->delete();

        return $this->recordCompletedAction($incident, $event, 'terminate_sessions', $context, [
            'user_id' => $user->id,
            'deleted_sessions' => $count,
        ], null, null, $user);
    }

    private function revokeApiToken(SecurityIncident $incident, SecurityEvent $event, array $context): SecurityAction
    {
        if (!(bool) config('security.api.revoke_token_on_ip_anomaly', true)) {
            return $this->recordCompletedAction($incident, $event, 'revoke_api_token', $context, [
                'message' => 'Token revocation policy is disabled in Security Center settings.',
            ]);
        }

        $apiKey = $context['api_key'] ?? null;
        if ($apiKey instanceof ApiKey) {
            $identifier = $apiKey->identifier;
            $user = $apiKey->user;
            $apiKey->delete();

            return $this->recordCompletedAction($incident, $event, 'revoke_api_token', $context, [
                'identifier' => $identifier,
            ], null, null, $user, $apiKey);
        }

        $user = $this->resolveUser($context);
        if (!$user) {
            return $this->recordFailedAction($incident, $event, 'revoke_api_token', $context, 'No API key or user context was available.');
        }

        $count = ApiKey::query()->where('user_id', $user->id)->delete();

        return $this->recordCompletedAction($incident, $event, 'revoke_api_token', $context, [
            'user_id' => $user->id,
            'deleted_keys' => $count,
        ], null, null, $user);
    }

    private function holdRoute(SecurityIncident $incident, SecurityEvent $event, array $context): SecurityAction
    {
        $route = trim((string) ($context['route'] ?? data_get($event->evidence, 'route', '')));
        if ($route === '') {
            return $this->recordFailedAction($incident, $event, 'hold_route', $context, 'No route identifier was available.');
        }

        $this->runtime->holdRoute($route, null, [
            'incident_id' => $incident->id,
            'reason' => $incident->title,
        ]);

        return $this->recordCompletedAction($incident, $event, 'hold_route', $context, [
            'route' => $route,
        ]);
    }

    private function containAgent(SecurityIncident $incident, SecurityEvent $event, array $context): SecurityAction
    {
        $agent = $context['agent'] ?? null;
        if (!$agent instanceof SecurityAgent) {
            return $this->recordFailedAction($incident, $event, 'contain', $context, 'No security agent was attached to this incident.');
        }

        $agent->forceFill([
            'status' => SecurityVocabulary::AGENT_ISOLATED,
            'isolated_at' => now(),
        ])->saveOrFail();

        return SecurityAction::query()->create([
            'agent_id' => $agent->id,
            'incident_id' => $incident->id,
            'event_id' => $event->id,
            'action' => 'contain',
            'scope' => 'agent',
            'status' => SecurityVocabulary::ACTION_PENDING,
            'payload' => [
                'reason' => $incident->title,
                'verdict' => $incident->verdict,
                'mitigation_stage' => $incident->mitigation_stage,
                'capabilities' => Arr::wrap(data_get($context, 'capabilities', [])),
            ],
            'execute_after' => now(),
        ]);
    }

    private function quarantineArtifact(SecurityIncident $incident, SecurityEvent $event, array $context): SecurityAction
    {
        $target = $context['target'] ?? null;
        if (!$target instanceof EloquentModel) {
            return $this->recordFailedAction($incident, $event, 'quarantine_artifact', $context, 'No target model was available for quarantine.');
        }

        $artifact = $this->quarantine->quarantine($target, [
            'incident_id' => $incident->id,
            'event_id' => $event->id,
            'disk' => data_get($context, 'quarantine.disk'),
            'path' => data_get($context, 'quarantine.path'),
            'original_name' => data_get($context, 'quarantine.original_name'),
            'sha256' => data_get($context, 'quarantine.sha256'),
            'reason' => data_get($context, 'quarantine.reason', $incident->title),
            'meta' => data_get($context, 'quarantine.meta'),
        ]);

        return $this->recordCompletedAction($incident, $event, 'quarantine_artifact', $context, [
            'artifact_id' => $artifact->id,
            'target_type' => $artifact->target_type,
            'target_id' => $artifact->target_id,
        ], null, null, null, $target);
    }

    private function recordCompletedAction(
        SecurityIncident $incident,
        SecurityEvent $event,
        string $action,
        array $context,
        array $result = [],
        ?string $sourceIp = null,
        ?string $fingerprint = null,
        ?User $user = null,
        ?EloquentModel $target = null,
    ): SecurityAction {
        return SecurityAction::query()->create([
            'agent_id' => ($context['agent'] ?? null) instanceof SecurityAgent ? $context['agent']->id : null,
            'incident_id' => $incident->id,
            'event_id' => $event->id,
            'action' => $action,
            'scope' => $context['scope'] ?? null,
            'target_type' => $target?->getMorphClass(),
            'target_id' => $target?->getKey(),
            'source_ip' => $sourceIp,
            'fingerprint' => $fingerprint,
            'payload' => $context['action_payload'] ?? null,
            'status' => SecurityVocabulary::ACTION_COMPLETED,
            'result' => $result,
            'execute_after' => now(),
            'acknowledged_at' => now(),
            'completed_at' => now(),
        ]);
    }

    private function recordFailedAction(SecurityIncident $incident, SecurityEvent $event, string $action, array $context, string $message): SecurityAction
    {
        return SecurityAction::query()->create([
            'agent_id' => ($context['agent'] ?? null) instanceof SecurityAgent ? $context['agent']->id : null,
            'incident_id' => $incident->id,
            'event_id' => $event->id,
            'action' => $action,
            'scope' => $context['scope'] ?? null,
            'status' => SecurityVocabulary::ACTION_FAILED,
            'payload' => $context['action_payload'] ?? null,
            'result' => ['message' => $message],
            'execute_after' => now(),
            'acknowledged_at' => now(),
            'completed_at' => now(),
        ]);
    }

    private function resolveUser(array $context): ?User
    {
        $actor = $context['actor'] ?? null;
        if ($actor instanceof User) {
            return $actor;
        }

        $target = $context['target'] ?? null;
        if ($target instanceof User) {
            return $target;
        }

        return null;
    }
}
