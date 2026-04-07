<?php

namespace Pterodactyl\Services\Security;

class SecurityVocabulary
{
    public const VERDICT_BLOCKED = 'blocked';
    public const VERDICT_CHALLENGED = 'challenged';
    public const VERDICT_RATE_LIMITED = 'rate_limited';
    public const VERDICT_CONTAINED = 'contained';
    public const VERDICT_QUARANTINED = 'quarantined';
    public const VERDICT_OBSERVED = 'observed_only';
    public const VERDICT_FAILED_TO_BLOCK = 'failed_to_block';
    public const VERDICT_NOT_CONTROLLABLE = 'not_controllable_at_origin';

    public const STAGE_OBSERVE = 'observe';
    public const STAGE_CHALLENGE = 'challenge';
    public const STAGE_RATE_LIMIT = 'rate_limit';
    public const STAGE_TEMP_BLOCK = 'temp_block';
    public const STAGE_CONTAIN = 'contain';
    public const STAGE_QUARANTINE = 'quarantine';

    public const STATUS_OPEN = 'open';
    public const STATUS_MONITORING = 'monitoring';
    public const STATUS_CONTAINED = 'contained';
    public const STATUS_RESOLVED = 'resolved';

    public const AGENT_PROVISIONING = 'provisioning';
    public const AGENT_ACTIVE = 'active';
    public const AGENT_STALE = 'stale';
    public const AGENT_ISOLATED = 'isolated';
    public const AGENT_DISABLED = 'disabled';

    public const ACTION_PENDING = 'pending';
    public const ACTION_DISPATCHED = 'dispatched';
    public const ACTION_COMPLETED = 'completed';
    public const ACTION_FAILED = 'failed';
    public const ACTION_CANCELLED = 'cancelled';

    public static function verdicts(): array
    {
        return [
            self::VERDICT_BLOCKED,
            self::VERDICT_CHALLENGED,
            self::VERDICT_RATE_LIMITED,
            self::VERDICT_CONTAINED,
            self::VERDICT_QUARANTINED,
            self::VERDICT_OBSERVED,
            self::VERDICT_FAILED_TO_BLOCK,
            self::VERDICT_NOT_CONTROLLABLE,
        ];
    }

    public static function stages(): array
    {
        return [
            self::STAGE_OBSERVE,
            self::STAGE_CHALLENGE,
            self::STAGE_RATE_LIMIT,
            self::STAGE_TEMP_BLOCK,
            self::STAGE_CONTAIN,
            self::STAGE_QUARANTINE,
        ];
    }

    public static function incidentStatuses(): array
    {
        return [
            self::STATUS_OPEN,
            self::STATUS_MONITORING,
            self::STATUS_CONTAINED,
            self::STATUS_RESOLVED,
        ];
    }

    public static function actionStatuses(): array
    {
        return [
            self::ACTION_PENDING,
            self::ACTION_DISPATCHED,
            self::ACTION_COMPLETED,
            self::ACTION_FAILED,
            self::ACTION_CANCELLED,
        ];
    }

    public static function severityRank(string $severity): int
    {
        return match (strtolower(trim($severity))) {
            'critical' => 4,
            'high' => 3,
            'medium' => 2,
            default => 1,
        };
    }

    public static function stageForScore(int $score, int $threshold, array $policy = []): string
    {
        $threshold = max(1, $threshold);
        $policy = array_map('strval', $policy);

        if (in_array('quarantine_artifact', $policy, true) && $score >= $threshold) {
            return self::STAGE_QUARANTINE;
        }

        if ($score >= ($threshold * 3)) {
            return self::STAGE_CONTAIN;
        }

        if ($score >= ($threshold * 2)) {
            return self::STAGE_TEMP_BLOCK;
        }

        if ($score >= (int) ceil($threshold * 1.5)) {
            return self::STAGE_RATE_LIMIT;
        }

        if ($score >= $threshold) {
            return self::STAGE_CHALLENGE;
        }

        return self::STAGE_OBSERVE;
    }

    public static function verdictForStage(string $stage, bool $blocked = false): string
    {
        return match ($stage) {
            self::STAGE_CHALLENGE => self::VERDICT_CHALLENGED,
            self::STAGE_RATE_LIMIT => self::VERDICT_RATE_LIMITED,
            self::STAGE_TEMP_BLOCK => $blocked ? self::VERDICT_BLOCKED : self::VERDICT_FAILED_TO_BLOCK,
            self::STAGE_CONTAIN => self::VERDICT_CONTAINED,
            self::STAGE_QUARANTINE => self::VERDICT_QUARANTINED,
            default => self::VERDICT_OBSERVED,
        };
    }
}
