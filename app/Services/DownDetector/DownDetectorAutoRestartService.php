<?php

namespace Pterodactyl\Services\DownDetector;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Jobs\DownDetector\RestartCrashedServerJob;
use Pterodactyl\Models\DownDetector\DownDetectorIncident;
use Pterodactyl\Models\DownDetector\DownDetectorMonitor;
use Pterodactyl\Models\Server;

class DownDetectorAutoRestartService
{
    public function __construct(private DownDetectorSettingsService $settings)
    {
    }

    public function defaults(): array
    {
        $config = $this->settings->config();

        return [
            'enabled' => (bool) data_get($config, 'server.auto_restart_default_enabled', false),
            'delay_seconds' => max((int) data_get($config, 'server.auto_restart_delay_seconds', 30), 10),
            'max_attempts' => max((int) data_get($config, 'server.auto_restart_max_attempts', 3), 1),
            'window_minutes' => max((int) data_get($config, 'server.auto_restart_window_minutes', 15), 1),
        ];
    }

    public function defaultEnabled(): bool
    {
        return (bool) $this->defaults()['enabled'];
    }

    public function recordManualIntent(Server $server, string $signal): void
    {
        Cache::forever($this->intentCacheKey($server->id), [
            'signal' => $signal,
            'recorded_at' => now()->toIso8601String(),
        ]);

        Activity::event(sprintf('server:auto_restart.intent.%s', strtolower($signal)))
            ->subject($server)
            ->property([
                'signal' => strtolower($signal),
                'recorded_at' => now()->toIso8601String(),
            ])
            ->log();
    }

    public function clearStopIntentIfRunning(Server $server): void
    {
        $intent = $this->latestIntent($server);
        if (!$intent) {
            return;
        }

        if (in_array((string) ($intent['signal'] ?? ''), ['stop', 'kill'], true)) {
            Cache::forget($this->intentCacheKey($server->id));
        }
    }

    public function ignoredObservationForPowerIntent(
        Server $server,
        ?array $latestPowerEvent,
        CarbonImmutable $now,
        int $intervalSeconds
    ): ?array {
        $intent = $this->latestIntent($server);
        if (is_array($intent)) {
            $signal = (string) ($intent['signal'] ?? '');
            $recordedAt = isset($intent['recorded_at']) ? CarbonImmutable::parse((string) $intent['recorded_at']) : null;

            if (in_array($signal, ['stop', 'kill'], true)) {
                return $this->ignoredObservation(
                    'manual_stop',
                    sprintf('Server `%s` was stopped manually.', $server->name)
                );
            }

            if (in_array($signal, ['start', 'restart'], true) && $recordedAt instanceof CarbonImmutable) {
                if ($recordedAt->greaterThanOrEqualTo($now->subSeconds($this->manualStartGraceWindow($intervalSeconds)))) {
                    return $this->ignoredObservation(
                        $signal === 'restart' ? 'manual_restart' : 'manual_start',
                        sprintf('Server `%s` is processing a recent manual %s request.', $server->name, $signal)
                    );
                }
            }
        }

        if (!$latestPowerEvent) {
            return null;
        }

        $event = (string) ($latestPowerEvent['event'] ?? '');
        $timestamp = $latestPowerEvent['timestamp'] ?? null;
        if (!$timestamp instanceof CarbonImmutable) {
            return null;
        }

        if ($timestamp->lessThan($now->subSeconds($this->manualStartGraceWindow($intervalSeconds)))) {
            return null;
        }

        return match ($event) {
            'server:power.stop', 'server:power.kill' => $this->ignoredObservation(
                'manual_stop',
                sprintf('Server `%s` was stopped manually.', $server->name)
            ),
            'server:power.restart' => $this->ignoredObservation(
                'manual_restart',
                sprintf('Server `%s` is processing a recent manual restart request.', $server->name)
            ),
            'server:power.start' => $this->ignoredObservation(
                'manual_start',
                sprintf('Server `%s` is processing a recent manual start request.', $server->name)
            ),
            default => null,
        };
    }

    public function handleDownTransition(
        Server $server,
        DownDetectorMonitor $monitor,
        DownDetectorIncident $incident,
        array $observation,
        CarbonImmutable $checkedAt
    ): void {
        if (!$server->auto_restart_on_crash) {
            return;
        }

        if ($this->hasPendingRestart($server->id)) {
            $this->markIncidentOutcome($incident, 'pending', [
                'message' => 'A crash recovery restart is already queued for this server.',
            ]);

            Activity::event('server:auto_restart.skipped')
                ->subject($server)
                ->property([
                    'reason' => 'pending',
                    'incident_id' => $incident->id,
                ])
                ->log();

            return;
        }

        if ($this->hasExceededAttemptLimit($server)) {
            $this->markIncidentOutcome($incident, 'skipped_limit_reached', [
                'message' => 'The auto restart retry window has already been exhausted for this server.',
            ]);

            Activity::event('server:auto_restart.rate_limited')
                ->subject($server)
                ->property([
                    'incident_id' => $incident->id,
                    'server_id' => $server->id,
                ])
                ->log();

            return;
        }

        $defaults = $this->defaults();

        $this->setPendingRestart($server->id, [
            'incident_id' => $incident->id,
            'queued_at' => $checkedAt->toIso8601String(),
        ]);

        RestartCrashedServerJob::dispatch(
            $server->id,
            $monitor->id,
            $incident->id,
            $checkedAt->toIso8601String()
        )->delay(now()->addSeconds($defaults['delay_seconds']));

        $this->markIncidentOutcome($incident, 'queued', [
            'delay_seconds' => $defaults['delay_seconds'],
            'message' => 'A delayed crash recovery restart has been queued.',
            'reason' => $observation['reason'] ?? null,
        ]);

        Activity::event('server:auto_restart.queued')
            ->subject($server)
            ->property([
                'incident_id' => $incident->id,
                'server_id' => $server->id,
                'delay_seconds' => $defaults['delay_seconds'],
            ])
            ->log();
    }

    public function markIncidentOutcome(DownDetectorIncident $incident, string $outcome, array $meta = []): void
    {
        $existing = is_array($incident->meta) ? $incident->meta : [];
        $incident->forceFill([
            'meta' => array_merge($existing, [
                'auto_restart' => array_merge([
                    'outcome' => $outcome,
                    'updated_at' => now()->toIso8601String(),
                ], $meta),
            ]),
        ])->save();
    }

    public function hasExceededAttemptLimit(Server $server): bool
    {
        return count($this->recentRestartAttempts($server->id)) >= $this->defaults()['max_attempts'];
    }

    public function recordRestartAttempt(Server $server): void
    {
        $timestamps = $this->recentRestartAttempts($server->id);
        $timestamps[] = now()->toIso8601String();

        Cache::put(
            $this->attemptCacheKey($server->id),
            $timestamps,
            now()->addMinutes($this->defaults()['window_minutes'] + 10)
        );
    }

    public function clearPendingRestart(int $serverId): void
    {
        Cache::forget($this->pendingCacheKey($serverId));
    }

    public function hasPendingRestart(int $serverId): bool
    {
        return Cache::has($this->pendingCacheKey($serverId));
    }

    private function setPendingRestart(int $serverId, array $payload): void
    {
        $ttlMinutes = max((int) ceil(($this->defaults()['delay_seconds'] + 300) / 60), 10);
        Cache::put($this->pendingCacheKey($serverId), $payload, now()->addMinutes($ttlMinutes));
    }

    private function latestIntent(Server $server): ?array
    {
        $value = Cache::get($this->intentCacheKey($server->id));

        return is_array($value) ? $value : null;
    }

    private function recentRestartAttempts(int $serverId): array
    {
        $windowStart = now()->subMinutes($this->defaults()['window_minutes']);
        $value = Cache::get($this->attemptCacheKey($serverId), []);

        $timestamps = array_values(array_filter((array) $value, function ($timestamp) use ($windowStart) {
            try {
                return CarbonImmutable::parse((string) $timestamp)->greaterThanOrEqualTo($windowStart);
            } catch (\Throwable) {
                return false;
            }
        }));

        if ($timestamps !== $value) {
            Cache::put(
                $this->attemptCacheKey($serverId),
                $timestamps,
                now()->addMinutes($this->defaults()['window_minutes'] + 10)
            );
        }

        return $timestamps;
    }

    private function manualStartGraceWindow(int $intervalSeconds): int
    {
        return max($intervalSeconds * 3, 300);
    }

    private function ignoredObservation(string $reason, string $summary): array
    {
        return [
            'status' => DownDetectorMonitor::STATUS_IGNORED,
            'reason' => $reason,
            'message' => $summary,
            'meta' => ['probe' => 'manual_power_intent'],
            'summary' => $summary,
        ];
    }

    private function intentCacheKey(int $serverId): string
    {
        return sprintf('server:auto_restart:intent:%d', $serverId);
    }

    private function pendingCacheKey(int $serverId): string
    {
        return sprintf('server:auto_restart:pending:%d', $serverId);
    }

    private function attemptCacheKey(int $serverId): string
    {
        return sprintf('server:auto_restart:attempts:%d', $serverId);
    }
}
