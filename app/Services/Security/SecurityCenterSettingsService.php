<?php

namespace Pterodactyl\Services\Security;

use Illuminate\Support\Arr;

class SecurityCenterSettingsService
{
    public function enabled(): bool
    {
        return (bool) config('security.enabled', true);
    }

    public function trustedNetworks(): array
    {
        return $this->uniqueStrings(array_merge(
            Arr::wrap(config('security.trusted_ips', [])),
            Arr::wrap(config('security.trusted_networks', []))
        ));
    }

    public function breakGlassTrustedNetworks(): array
    {
        return $this->uniqueStrings(Arr::wrap(config('security.break_glass.trusted_networks', [])));
    }

    public function emergencyToken(): ?string
    {
        return $this->nullableString(config('security.break_glass.emergency_token'));
    }

    public function runtimeIpDenyMinutes(): int
    {
        return max(1, (int) config('security.runtime.ip_deny_minutes', 60));
    }

    public function runtimeFingerprintDenyMinutes(): int
    {
        return max(1, (int) config('security.runtime.fingerprint_deny_minutes', 60));
    }

    public function runtimeRouteHoldMinutes(): int
    {
        return max(1, (int) config('security.runtime.route_hold_minutes', 15));
    }

    public function uploadSuspiciousExtensions(): array
    {
        return $this->uniqueStrings(Arr::wrap(config('security.upload.suspicious_extensions', [])));
    }

    public function config(): array
    {
        return [
            'general' => [
                'enabled' => (bool) config('security.enabled', true),
                'trusted_networks' => $this->trustedNetworks(),
            ],
            'break_glass' => [
                'trusted_networks' => $this->breakGlassTrustedNetworks(),
                'emergency_token' => $this->emergencyToken(),
            ],
            'runtime' => [
                'ip_deny_minutes' => $this->runtimeIpDenyMinutes(),
                'fingerprint_deny_minutes' => $this->runtimeFingerprintDenyMinutes(),
                'route_hold_minutes' => $this->runtimeRouteHoldMinutes(),
            ],
            'auth' => [
                'enabled' => (bool) config('security.auth.enabled', true),
                'auto_challenge' => (bool) config('security.auth.auto_challenge', true),
                'auto_temp_block' => (bool) config('security.auth.auto_temp_block', true),
            ],
            'api' => [
                'enabled' => (bool) config('security.api.enabled', true),
                'revoke_token_on_ip_anomaly' => (bool) config('security.api.revoke_token_on_ip_anomaly', true),
            ],
            'bridge' => [
                'enabled' => (bool) config('security.bridge.enabled', true),
            ],
            'webhook' => [
                'enabled' => (bool) config('security.webhook.enabled', true),
            ],
            'upload' => [
                'enabled' => (bool) config('security.upload.enabled', true),
                'quarantine_on_suspicious' => (bool) config('security.upload.quarantine_on_suspicious', true),
                'suspicious_extensions' => $this->uploadSuspiciousExtensions(),
            ],
            'origin' => [
                'enabled' => (bool) config('security.origin.enabled', true),
                'not_controllable_threshold' => max(100, (int) config('security.origin.not_controllable_threshold', 5000)),
            ],
            'agent' => [
                'enabled' => (bool) config('security.agent.enabled', true),
                'heartbeat_ttl_seconds' => max(60, (int) config('security.agent.heartbeat_ttl_seconds', 180)),
                'clock_skew_seconds' => max(5, (int) config('security.agent.clock_skew_seconds', 90)),
                'secret_rotation_grace_seconds' => max(60, (int) config('security.agent.secret_rotation_grace_seconds', 3600)),
                'nonce_ttl_seconds' => max(30, (int) config('security.agent.nonce_ttl_seconds', 300)),
            ],
            'retention' => [
                'event_days' => max(1, (int) config('security.retention.event_days', 30)),
                'incident_days' => max(1, (int) config('security.retention.incident_days', 90)),
            ],
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function uniqueStrings(array $values): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn (mixed $value) => trim((string) $value),
            $values
        ))));
    }
}
