<?php

namespace Pterodactyl\Services\Security;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Pterodactyl\Models\Node;
use Pterodactyl\Models\Security\SecurityEvent;
use Pterodactyl\Models\Security\SecurityIncident;
use Pterodactyl\Models\Security\SecurityRule;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Ticket;
use Pterodactyl\Models\User;

class SecurityEventFormatterService
{
    public function activityProperties(
        SecurityRule $rule,
        SecurityEvent $event,
        SecurityIncident $incident,
        ?Model $actor = null,
        ?Model $target = null,
    ): array {
        $summary = $this->describe($event, $incident, $rule, $actor, $target);

        return [
            'attack_name' => $summary['attack_name'],
            'attack_key' => $rule->key,
            'attack_class' => $summary['attack_class'],
            'status_line' => $summary['status_line'],
            'summary' => $summary['summary'],
            'outcome' => $summary['outcome'],
            'protection_status' => $summary['protection_status'],
            'evidence_summary' => $summary['evidence_summary'],
            'surface' => $event->surface,
            'class' => $event->class,
            'severity' => $event->severity,
            'confidence' => $event->confidence,
            'verdict' => $event->verdict,
            'blocked' => $event->blocked,
            'mitigation_stage' => $event->mitigation_stage,
            'source_ip' => $event->source_ip,
            'fingerprint' => $event->fingerprint,
            'actor_label' => $summary['actor_label'],
            'target_label' => $summary['target_label'],
            'node_label' => $summary['node_label'],
            'threat_id' => $incident->threat_id,
            'incident_id' => $incident->id,
            'correlation_id' => $event->correlation_id,
        ];
    }

    public function logRow(SecurityEvent $event): array
    {
        $summary = $this->describe(
            $event,
            $event->relationLoaded('incident') ? $event->incident : null,
            $event->relationLoaded('rule') ? $event->rule : null,
            $event->relationLoaded('actor') ? $event->actor : null,
            $event->relationLoaded('target') ? $event->target : null,
        );

        return [
            'sort_at' => optional($event->created_at)?->timestamp ?? 0,
            'timestamp' => optional($event->created_at)?->format('Y-m-d H:i:s T') ?? 'Unknown',
            'event' => $event->rule?->key ?? $event->class,
            'attack' => $summary['attack_name'],
            'outcome' => sprintf('%s (%s)', $summary['outcome'], $summary['protection_status']),
            'source' => $summary['source_label'],
            'target' => $summary['target_label'],
            'details' => Str::limit(trim(implode(' ', array_filter([
                $summary['summary'],
                $summary['status_line'],
                $summary['evidence_summary'] !== 'No extra evidence recorded.' ? 'Evidence: ' . $summary['evidence_summary'] : null,
            ]))), 260, '...'),
        ];
    }

    public function describe(
        SecurityEvent $event,
        ?SecurityIncident $incident = null,
        ?SecurityRule $rule = null,
        ?Model $actor = null,
        ?Model $target = null,
    ): array {
        $incident ??= $event->relationLoaded('incident') ? $event->incident : null;
        $rule ??= $event->relationLoaded('rule') ? $event->rule : null;
        $actor ??= $event->relationLoaded('actor') ? $event->actor : null;
        $target ??= $event->relationLoaded('target') ? $event->target : null;

        $node = $event->relationLoaded('node') ? $event->node : null;
        $evidence = Arr::wrap($event->evidence);
        $attackName = $this->attackName($rule, $event, $incident, $evidence);
        $attackClass = $rule?->class ?: ($incident?->class ?: $event->class);
        $summary = trim((string) ($incident?->summary ?: $rule?->description ?: 'Security event recorded.'));
        $outcome = $this->outcomeLabel($event->verdict);
        $protectionStatus = $this->protectionStatus($event->verdict);
        $evidenceSummary = $this->evidenceSummary($rule?->key, $evidence);
        $sourceLabel = $this->sourceLabel($event, $actor, $node);
        $targetLabel = $this->targetLabel($target, $event);
        $actorLabel = $this->modelLabel($actor, $event->actor_type, $event->actor_id, 'System / Anonymous');
        $nodeLabel = $this->nodeLabel($node, $event);

        return [
            'attack_name' => $attackName,
            'attack_class' => $attackClass,
            'summary' => $summary,
            'outcome' => $outcome,
            'protection_status' => $protectionStatus,
            'status_line' => sprintf('Protection result: %s. Verdict: %s at stage %s.', $protectionStatus, $outcome, Str::headline($event->mitigation_stage)),
            'evidence_summary' => $evidenceSummary,
            'source_label' => $sourceLabel,
            'target_label' => $targetLabel,
            'actor_label' => $actorLabel,
            'node_label' => $nodeLabel,
        ];
    }

    private function attackName(?SecurityRule $rule, SecurityEvent $event, ?SecurityIncident $incident, array $evidence): string
    {
        $reason = strtolower((string) Arr::get($evidence, 'reason', ''));
        $provider = strtolower((string) Arr::get($evidence, 'provider', ''));

        return match ($rule?->key) {
            'auth_brute_force' => 'Authentication Brute Force / Credential Stuffing',
            'auth_checkpoint_abuse' => match ($reason) {
                'captcha_missing' => 'Captcha Challenge Bypass Attempt',
                'captcha_failed' => 'Captcha Validation Abuse',
                'failed_password_reset' => 'Password Reset Abuse',
                'failed_signup_verify' => 'Signup Verification Abuse',
                default => 'Authentication Checkpoint Abuse',
            },
            'auth_honeypot_trigger' => 'Authentication Honeypot Trigger',
            'auth_honeyport_probe' => 'Honeyport Probe',
            'api_ip_anomaly' => 'API Token IP Anomaly',
            'bridge_signature_failure' => 'Discord Bridge Forgery / Replay Attempt',
            'webhook_signature_failure' => $provider !== ''
                ? Str::headline($provider) . ' Webhook Forgery Attempt'
                : 'Webhook Forgery Attempt',
            'upload_suspicious_attachment' => 'Suspicious Attachment / Malware-Style Upload',
            'agent_signature_failure' => 'Security Agent Forgery / Replay Attempt',
            'agent_silence' => 'Security Agent Silence / Drift',
            'origin_exhaustion' => 'Origin Exhaustion / Volumetric DDoS Pressure',
            'runtime_policy_block' => 'Runtime Security Policy Block',
            'security_self_test_run' => 'Security Self-Test Validation',
            default => $incident?->title ?: ($rule?->name ?: Str::headline(str_replace('_', ' ', $event->class))),
        };
    }

    private function outcomeLabel(string $verdict): string
    {
        return match ($verdict) {
            SecurityVocabulary::VERDICT_BLOCKED => 'Blocked',
            SecurityVocabulary::VERDICT_CHALLENGED => 'Challenged',
            SecurityVocabulary::VERDICT_RATE_LIMITED => 'Rate Limited',
            SecurityVocabulary::VERDICT_CONTAINED => 'Contained',
            SecurityVocabulary::VERDICT_QUARANTINED => 'Quarantined',
            SecurityVocabulary::VERDICT_FAILED_TO_BLOCK => 'Failed to Block',
            SecurityVocabulary::VERDICT_NOT_CONTROLLABLE => 'Not Controllable at Origin',
            default => 'Observed Only',
        };
    }

    private function protectionStatus(string $verdict): string
    {
        return match ($verdict) {
            SecurityVocabulary::VERDICT_BLOCKED,
            SecurityVocabulary::VERDICT_CONTAINED,
            SecurityVocabulary::VERDICT_QUARANTINED => 'Stopped',
            SecurityVocabulary::VERDICT_CHALLENGED,
            SecurityVocabulary::VERDICT_RATE_LIMITED => 'Partially Controlled',
            SecurityVocabulary::VERDICT_FAILED_TO_BLOCK => 'Not Stopped',
            SecurityVocabulary::VERDICT_NOT_CONTROLLABLE => 'Upstream Mitigation Required',
            default => 'Observed Only',
        };
    }

    private function sourceLabel(SecurityEvent $event, ?Model $actor, ?Node $node): string
    {
        $parts = [];

        if ($event->source_ip) {
            $parts[] = $event->source_ip;
        }

        $actorLabel = $this->modelLabel($actor, $event->actor_type, $event->actor_id, '');
        if ($actorLabel !== '') {
            $parts[] = $actorLabel;
        }

        $nodeLabel = $this->nodeLabel($node, $event);
        if ($nodeLabel !== '') {
            $parts[] = 'Node ' . $nodeLabel;
        }

        return $parts !== [] ? implode(' | ', array_unique($parts)) : 'Unknown source';
    }

    private function targetLabel(?Model $target, SecurityEvent $event): string
    {
        return $this->modelLabel($target, $event->target_type, $event->target_id, $event->threat_id ?: 'n/a');
    }

    private function nodeLabel(?Node $node, SecurityEvent $event): string
    {
        if ($node instanceof Node) {
            return sprintf('%s (#%d)', $node->name, $node->id);
        }

        return $event->node_id ? 'Node #' . $event->node_id : '';
    }

    private function modelLabel(?Model $model, ?string $type, mixed $id, string $fallback): string
    {
        return match (true) {
            $model instanceof User => sprintf('%s (%s)', $model->username, $model->email),
            $model instanceof Ticket => $model->ticket_number,
            $model instanceof Server => sprintf('%s (%s)', $model->name, $model->uuidShort),
            $model instanceof Node => sprintf('%s (#%d)', $model->name, $model->id),
            $model instanceof Model => class_basename($model) . ' #' . $model->getKey(),
            is_string($type) && $type !== '' && is_numeric($id) => class_basename($type) . ' #' . (int) $id,
            default => $fallback,
        };
    }

    private function evidenceSummary(?string $ruleKey, array $evidence): string
    {
        $reason = strtolower((string) Arr::get($evidence, 'reason', ''));
        $signals = array_values(array_filter(array_map('strval', Arr::wrap(Arr::get($evidence, 'signals', [])))));
        $checks = array_values(array_filter(Arr::wrap(Arr::get($evidence, 'checks', [])), static fn (mixed $entry) => is_array($entry)));

        return match ($ruleKey) {
            'auth_brute_force', 'auth_checkpoint_abuse', 'auth_honeypot_trigger' => $this->implodeSummaryParts([
                $reason !== '' ? 'Signal: ' . $this->reasonLabel($reason) : null,
                Arr::has($evidence, 'score') ? 'Score: ' . (int) Arr::get($evidence, 'score') : null,
                Arr::has($evidence, 'points') ? 'Points: ' . (int) Arr::get($evidence, 'points') : null,
                ($route = trim((string) Arr::get($evidence, 'route', ''))) !== '' ? 'Route: ' . $route : null,
            ]),
            'bridge_signature_failure', 'agent_signature_failure' => $this->implodeSummaryParts([
                ($path = trim((string) Arr::get($evidence, 'path', ''))) !== '' ? 'Path: ' . $path : null,
                Arr::has($evidence, 'secret_configured') ? 'Secret configured: ' . ((bool) Arr::get($evidence, 'secret_configured') ? 'yes' : 'no') : null,
                ($prefix = trim((string) Arr::get($evidence, 'provided_signature_prefix', ''))) !== '' ? 'Signature prefix: ' . $prefix : null,
                ($message = trim((string) Arr::get($evidence, 'message', ''))) !== '' ? 'Message: ' . $message : null,
            ]),
            'webhook_signature_failure' => $this->implodeSummaryParts([
                ($provider = trim((string) Arr::get($evidence, 'provider', ''))) !== '' ? 'Provider: ' . $provider : null,
                ($message = trim((string) Arr::get($evidence, 'message', ''))) !== '' ? 'Message: ' . $message : null,
            ]),
            'upload_suspicious_attachment' => $this->implodeSummaryParts([
                ($name = trim((string) Arr::get($evidence, 'original_name', ''))) !== '' ? 'File: ' . $name : null,
                $signals !== [] ? 'Signals: ' . implode(', ', $signals) : null,
                Arr::has($evidence, 'size_bytes') ? 'Size: ' . (int) Arr::get($evidence, 'size_bytes') . ' bytes' : null,
            ]),
            'security_self_test_run' => $this->selfTestEvidenceSummary($checks),
            default => $this->fallbackEvidenceSummary($evidence),
        };
    }

    private function selfTestEvidenceSummary(array $checks): string
    {
        $failed = array_values(array_filter($checks, static fn (array $check): bool => strtoupper((string) ($check['status'] ?? '')) === 'FAIL'));

        if ($failed === []) {
            return 'All security self-test checks passed.';
        }

        $names = array_map(
            static fn (array $check): string => Str::headline(str_replace('_', ' ', (string) ($check['name'] ?? 'unknown_check'))),
            array_slice($failed, 0, 3)
        );

        return sprintf('Failed checks: %s.', implode(', ', $names));
    }

    private function fallbackEvidenceSummary(array $evidence): string
    {
        $parts = [];

        foreach ($evidence as $key => $value) {
            if (in_array($key, ['checks', 'stdout', 'stderr', 'command'], true)) {
                continue;
            }

            if (is_array($value)) {
                $value = implode(', ', array_slice(array_map('strval', Arr::flatten($value)), 0, 4));
            }

            if (!is_scalar($value) && !is_null($value)) {
                continue;
            }

            $text = trim((string) $value);
            if ($text === '') {
                continue;
            }

            $parts[] = Str::headline((string) $key) . ': ' . Str::limit($text, 90, '...');

            if (count($parts) >= 4) {
                break;
            }
        }

        return $parts !== [] ? implode(' | ', $parts) : 'No extra evidence recorded.';
    }

    private function reasonLabel(string $reason): string
    {
        return match ($reason) {
            'failed_login' => 'Repeated failed login attempts',
            'captcha_missing' => 'Missing captcha token',
            'captcha_failed' => 'Failed captcha validation',
            'failed_password_reset' => 'Password reset abuse pattern',
            'failed_signup_verify' => 'Signup verification abuse pattern',
            'honeypot_triggered' => 'Honeypot trigger',
            default => Str::headline(str_replace('_', ' ', $reason)),
        };
    }

    private function implodeSummaryParts(array $parts): string
    {
        $parts = array_values(array_filter(array_map(
            static fn (mixed $value): ?string => is_string($value) && trim($value) !== '' ? trim($value) : null,
            $parts
        )));

        return $parts !== [] ? implode(' | ', $parts) : 'No extra evidence recorded.';
    }
}
