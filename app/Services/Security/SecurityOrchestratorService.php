<?php

namespace Pterodactyl\Services\Security;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Models\Security\SecurityEvent;
use Pterodactyl\Models\Security\SecurityIncident;
use Pterodactyl\Models\Security\SecurityRule;

class SecurityOrchestratorService
{
    public function __construct(
        private SecurityRuleBootstrapService $rules,
        private SecurityActionExecutorService $actions,
        private SecurityEventFormatterService $formatter,
    ) {
    }

    public function record(string $ruleKey, array $context = []): SecurityEvent
    {
        $rule = $this->rules->get($ruleKey);
        $score = $this->windowScore($rule, $context);
        $severity = $this->normalizeSeverity((string) ($context['severity'] ?? 'medium'));
        $confidence = max(0, min(100, (int) ($context['confidence'] ?? 70)));
        $stage = (string) ($context['mitigation_stage'] ?? SecurityVocabulary::stageForScore($score, $rule->threshold, Arr::wrap($rule->response_policy)));
        $blocked = array_key_exists('blocked', $context)
            ? (bool) $context['blocked']
            : in_array($stage, [
                SecurityVocabulary::STAGE_TEMP_BLOCK,
                SecurityVocabulary::STAGE_CONTAIN,
                SecurityVocabulary::STAGE_QUARANTINE,
            ], true);
        $verdict = (string) ($context['verdict'] ?? SecurityVocabulary::verdictForStage($stage, $blocked));
        $correlationId = (string) ($context['correlation_id'] ?? $this->correlationId($rule, $context));
        $threatId = (string) ($context['threat_id'] ?? substr(sha1($rule->key . '|' . $correlationId), 0, 64));
        $now = now();

        [$actorType, $actorId, $actorModel] = $this->morphContext($context['actor'] ?? null, $context['actor_type'] ?? null, $context['actor_id'] ?? null);
        [$targetType, $targetId, $targetModel] = $this->morphContext($context['target'] ?? null, $context['target_type'] ?? null, $context['target_id'] ?? null);

        $incident = SecurityIncident::query()->firstOrNew(['threat_id' => $threatId]);
        $incident->forceFill([
            'title' => (string) ($context['title'] ?? $rule->name),
            'summary' => $context['summary'] ?? $rule->description,
            'class' => (string) ($context['class'] ?? $rule->class),
            'surface' => (string) ($context['surface'] ?? $rule->surface),
            'status' => $this->incidentStatusFromStage($stage),
            'severity' => $this->maxSeverity($severity, (string) $incident->severity),
            'confidence' => max($confidence, (int) $incident->confidence),
            'source_ip' => $context['source_ip'] ?? $incident->source_ip,
            'fingerprint' => $context['fingerprint'] ?? $incident->fingerprint,
            'actor_type' => $actorType ?? $incident->actor_type,
            'actor_id' => $actorId ?? $incident->actor_id,
            'node_id' => $context['node_id'] ?? $incident->node_id,
            'target_type' => $targetType ?? $incident->target_type,
            'target_id' => $targetId ?? $incident->target_id,
            'evidence' => $this->mergeEvidence($incident->evidence ?? [], Arr::wrap($context['evidence'] ?? [])),
            'meta' => $this->mergeEvidence($incident->meta ?? [], [
                'last_rule_key' => $rule->key,
                'score' => $score,
            ]),
            'verdict' => $verdict,
            'blocked' => $blocked,
            'mitigation_stage' => $stage,
            'correlation_id' => $correlationId,
            'event_count' => $incident->exists ? ((int) $incident->event_count + 1) : 1,
            'first_seen_at' => $incident->first_seen_at ?? $now,
            'last_seen_at' => $now,
            'resolved_at' => null,
        ])->saveOrFail();

        $event = SecurityEvent::query()->create([
            'incident_id' => $incident->id,
            'rule_id' => $rule->id,
            'threat_id' => $threatId,
            'class' => (string) ($context['class'] ?? $rule->class),
            'surface' => (string) ($context['surface'] ?? $rule->surface),
            'severity' => $severity,
            'confidence' => $confidence,
            'source_ip' => $context['source_ip'] ?? null,
            'fingerprint' => $context['fingerprint'] ?? null,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'node_id' => $context['node_id'] ?? null,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'evidence' => Arr::wrap($context['evidence'] ?? []),
            'verdict' => $verdict,
            'blocked' => $blocked,
            'mitigation_stage' => $stage,
            'correlation_id' => $correlationId,
            'first_seen_at' => $now,
            'last_seen_at' => $now,
        ]);

        $this->logActivity($rule, $event, $incident, $actorModel, $targetModel);

        if (($context['execute_actions'] ?? true) && $rule->enabled && $rule->mode !== 'observe') {
            $this->actions->execute($rule, $incident, $event, $context + [
                'actor' => $actorModel,
                'target' => $targetModel,
                'source_ip' => $context['source_ip'] ?? null,
                'fingerprint' => $context['fingerprint'] ?? null,
            ]);
        }

        return $event->fresh(['incident', 'rule']);
    }

    private function windowScore(SecurityRule $rule, array $context): int
    {
        if (isset($context['score']) && is_numeric($context['score'])) {
            return (int) $context['score'];
        }

        $key = sprintf(
            'security:center:score:%s:%s',
            $rule->key,
            sha1($this->correlationId($rule, $context))
        );
        $current = (int) $this->cache()->get($key, 0);
        $next = $current + max(1, (int) $rule->weight);
        $this->cache()->put($key, $next, now()->addSeconds(max(1, (int) $rule->window_seconds)));

        return $next;
    }

    private function correlationId(SecurityRule $rule, array $context): string
    {
        $parts = [
            $rule->key,
            (string) ($context['source_ip'] ?? ''),
            (string) ($context['fingerprint'] ?? ''),
            (string) ($context['node_id'] ?? ''),
            (string) ($context['actor_id'] ?? ''),
            (string) ($context['target_id'] ?? ''),
            (string) data_get($context, 'route', ''),
        ];

        return substr(sha1(implode('|', $parts)), 0, 40);
    }

    private function incidentStatusFromStage(string $stage): string
    {
        return match ($stage) {
            SecurityVocabulary::STAGE_CONTAIN,
            SecurityVocabulary::STAGE_QUARANTINE => SecurityVocabulary::STATUS_CONTAINED,
            SecurityVocabulary::STAGE_CHALLENGE,
            SecurityVocabulary::STAGE_RATE_LIMIT,
            SecurityVocabulary::STAGE_TEMP_BLOCK => SecurityVocabulary::STATUS_MONITORING,
            default => SecurityVocabulary::STATUS_OPEN,
        };
    }

    private function normalizeSeverity(string $severity): string
    {
        $severity = strtolower(trim($severity));

        return in_array($severity, ['low', 'medium', 'high', 'critical'], true) ? $severity : 'medium';
    }

    private function maxSeverity(string $left, string $right): string
    {
        return SecurityVocabulary::severityRank($left) >= SecurityVocabulary::severityRank($right) ? $left : $right;
    }

    private function mergeEvidence(array $base, array $incoming): array
    {
        if ($incoming === []) {
            return $base;
        }

        return array_merge($base, $incoming);
    }

    private function logActivity(
        SecurityRule $rule,
        SecurityEvent $event,
        SecurityIncident $incident,
        ?EloquentModel $actor = null,
        ?EloquentModel $target = null,
    ): void {
        $activity = Activity::event('security:' . str_replace('_', '-', $rule->key))
            ->withRequestMetadata()
            ->property($this->formatter->activityProperties($rule, $event, $incident, $actor, $target));

        if ($actor instanceof EloquentModel) {
            $activity->subject($actor);
        }

        if ($target instanceof EloquentModel) {
            $activity->subject($target);
        }

        $activity->log($incident->title);
    }

    private function morphContext(mixed $model, mixed $type, mixed $id): array
    {
        if ($model instanceof EloquentModel) {
            return [$model->getMorphClass(), $model->getKey(), $model];
        }

        $type = is_string($type) && $type !== '' ? $type : null;
        $id = is_numeric($id) ? (int) $id : null;

        return [$type, $id, null];
    }

    private function cache(): CacheRepository
    {
        $cacheStore = config('security.cache_store');

        return $cacheStore ? Cache::store($cacheStore) : Cache::store();
    }
}
