<?php

namespace Pterodactyl\Services\DownDetector;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Models\Node;
use Pterodactyl\Models\Server;
use Throwable;
use Pterodactyl\Models\ActivityLogSubject;
use Pterodactyl\Models\DownDetector\DownDetectorIncident;
use Pterodactyl\Models\DownDetector\DownDetectorMonitor;

class DownDetectorRunnerService
{
    private const POWER_EVENTS = [
        'server:power.start',
        'server:power.restart',
        'server:power.stop',
        'server:power.kill',
    ];

    public function __construct(
        private DownDetectorSettingsService $settings,
        private DownDetectorProbeService $probe,
        private DownDetectorDiscordService $discord,
        private DownDetectorAutoRestartService $autoRestart,
    ) {
    }

    public function run(bool $force = false, bool $ignoreEnabled = false): array
    {
        $config = $this->settings->config();
        if (!$config['enabled'] && !$ignoreEnabled) {
            return ['skipped' => true, 'reason' => 'disabled'];
        }

        $now = CarbonImmutable::now();
        $lastRunAt = $config['last_run_at'] ? CarbonImmutable::parse($config['last_run_at']) : null;
        if (!$force && $lastRunAt && $lastRunAt->diffInSeconds($now) < $config['interval_seconds']) {
            return [
                'skipped' => true,
                'reason' => 'throttled',
                'next_run_after_seconds' => max(0, $config['interval_seconds'] - $lastRunAt->diffInSeconds($now)),
            ];
        }

        /** @var \Illuminate\Support\Collection<string, DownDetectorMonitor> $monitors */
        $monitors = DownDetectorMonitor::query()->get()->keyBy(
            fn (DownDetectorMonitor $monitor) => $this->monitorKey($monitor->monitorable_type, (int) $monitor->monitorable_id)
        );

        $changes = [
            'node' => ['down' => [], 'recovered' => []],
            'server' => ['down' => [], 'recovered' => []],
        ];
        $summary = [
            'checked_at' => $now->toIso8601String(),
            'nodes' => $this->emptySummaryBucket(),
            'servers' => $this->emptySummaryBucket(),
        ];

        if ($config['monitor_nodes']) {
            $nodeChanges = &$changes['node'];
            $this->runNodeChecks($now, $config, $monitors, $nodeChanges, $summary);
        }

        if ($config['monitor_servers']) {
            $serverChanges = &$changes['server'];
            $this->runServerChecks($now, $config, $monitors, $serverChanges, $summary);
        }

        $this->settings->saveRuntimeSummary($now, $summary);

        try {
            if ($config['monitor_nodes']) {
                $this->discord->sendDigest('node', $changes['node'], $now);
                $this->sendNodePeriodicSummaryIfDue($config, $summary, $now, $force);
            }

            if ($config['monitor_servers']) {
                $this->discord->sendDigest('server', $changes['server'], $now);
            }
        } catch (Throwable $exception) {
            report($exception);
            Log::warning('Down detector Discord digest failed.', ['exception' => $exception->getMessage()]);
        }

        return $summary + ['changes' => $changes, 'skipped' => false];
    }

    public function inspectServer(Server $server): array
    {
        $config = $this->settings->config();
        $server->loadMissing(['node', 'allocation', 'transfer']);

        $monitor = DownDetectorMonitor::query()
            ->where('monitorable_type', Server::class)
            ->where('monitorable_id', $server->id)
            ->first();

        if (!$monitor instanceof DownDetectorMonitor) {
            $monitor = new DownDetectorMonitor([
                'current_status' => DownDetectorMonitor::STATUS_UNKNOWN,
            ]);
        }

        $latestPowerEvent = $this->latestPowerEvents(collect([$server]))[$server->id] ?? null;

        return $this->observeServer(
            $server,
            $monitor,
            CarbonImmutable::now(),
            $config['probe_timeout_ms'],
            $latestPowerEvent,
            $config['interval_seconds']
        );
    }

    private function runNodeChecks(
        CarbonImmutable $now,
        array $config,
        Collection $monitors,
        array &$changes,
        array &$summary
    ): void {
        $nodes = Node::query()->orderBy('id')->get();

        foreach ($nodes as $node) {
            $monitor = $this->monitorFor($monitors, Node::class, $node->id);
            $observation = $this->observeNode($node, $config['probe_timeout_ms']);
            $status = $this->applyObservation(
                $monitor,
                $observation,
                $now,
                $config['failure_threshold'],
                $config['recovery_threshold'],
                $changes
            );

            $summary['nodes']['checked']++;
            $this->incrementSummaryStatus($summary['nodes'], $status);
        }
    }

    private function runServerChecks(
        CarbonImmutable $now,
        array $config,
        Collection $monitors,
        array &$changes,
        array &$summary
    ): void {
        Server::query()
            ->with(['node', 'allocation', 'transfer'])
            ->orderBy('id')
            ->chunkById(50, function (Collection $servers) use ($now, $config, $monitors, &$changes, &$summary) {
                $latestPowerEvents = $this->latestPowerEvents($servers);

                /** @var Server $server */
                foreach ($servers as $server) {
                    $monitor = $this->monitorFor($monitors, Server::class, $server->id);
                    $observation = $this->observeServer(
                        $server,
                        $monitor,
                        $now,
                        $config['probe_timeout_ms'],
                        $latestPowerEvents[$server->id] ?? null,
                        $config['interval_seconds']
                    );

                    $status = $this->applyObservation(
                        $monitor,
                        $observation,
                        $now,
                        $config['failure_threshold'],
                        $config['recovery_threshold'],
                        $changes
                    );

                    $summary['servers']['checked']++;
                    $this->incrementSummaryStatus($summary['servers'], $status);
                }
            });
    }

    private function observeNode(Node $node, int $timeoutMs): array
    {
        try {
            $payload = $this->probe->probeNode($node, $timeoutMs);

            return [
                'status' => DownDetectorMonitor::STATUS_UP,
                'reason' => null,
                'message' => sprintf('Wings reachable at %s.', $node->getConnectionAddress()),
                'meta' => [
                    'probe' => 'wings_system',
                    'connection' => $node->getConnectionAddress(),
                    'payload' => [
                        'version' => data_get($payload, 'version'),
                    ],
                ],
                'summary' => sprintf('Node `%s` is reachable again.', $node->name),
            ];
        } catch (Throwable $exception) {
            return [
                'status' => DownDetectorMonitor::STATUS_DOWN,
                'reason' => 'wings_unreachable',
                'message' => $exception->getMessage(),
                'meta' => [
                    'probe' => 'wings_system',
                    'connection' => $node->getConnectionAddress(),
                ],
                'summary' => sprintf(
                    'Node `%s` (`%s`) is down: %s',
                    $node->name,
                    $node->getConnectionAddress(),
                    $exception->getMessage()
                ),
            ];
        }
    }

    private function observeServer(
        Server $server,
        DownDetectorMonitor $monitor,
        CarbonImmutable $now,
        int $timeoutMs,
        ?array $latestPowerEvent,
        int $intervalSeconds
    ): array
    {
        if ($server->node->isUnderMaintenance()) {
            return $this->ignoredObservation('node_maintenance', sprintf(
                'Server `%s` ignored because node `%s` is under maintenance.',
                $server->name,
                $server->node->name
            ));
        }

        if ($server->isSuspended()) {
            return $this->ignoredObservation('suspended', sprintf('Server `%s` is suspended.', $server->name));
        }

        if (!$server->isInstalled() || in_array($server->status, [
            Server::STATUS_INSTALLING,
            Server::STATUS_INSTALL_FAILED,
            Server::STATUS_REINSTALL_FAILED,
            Server::STATUS_RESTORING_BACKUP,
        ], true)) {
            return $this->ignoredObservation('installing', sprintf('Server `%s` is currently installing or recovering.', $server->name));
        }

        if (!is_null($server->transfer)) {
            return $this->ignoredObservation('transferring', sprintf('Server `%s` is currently transferring.', $server->name));
        }

        try {
            $details = $this->probe->probeServer($server, $timeoutMs);
        } catch (Throwable $exception) {
            if ($ignored = $this->autoRestart->ignoredObservationForPowerIntent($server, $latestPowerEvent, $now, $intervalSeconds)) {
                return $ignored;
            }

            return [
                'status' => DownDetectorMonitor::STATUS_DOWN,
                'reason' => 'wings_unreachable',
                'message' => $exception->getMessage(),
                'meta' => [
                    'probe' => 'wings_server',
                    'server_uuid' => $server->uuid,
                ],
                'summary' => sprintf(
                    'Server `%s` (`%s`) is down because Wings is unreachable: %s',
                    $server->name,
                    $server->uuidShort,
                    $exception->getMessage()
                ),
            ];
        }

        $state = strtolower((string) data_get($details, 'state', 'unknown'));
        if ($state === 'starting') {
            return $this->ignoredObservation('starting', sprintf('Server `%s` is still starting.', $server->name));
        }

        if ($state !== 'running') {
            if ($ignored = $this->autoRestart->ignoredObservationForPowerIntent($server, $latestPowerEvent, $now, $intervalSeconds)) {
                return $ignored;
            }

            return [
                'status' => DownDetectorMonitor::STATUS_DOWN,
                'reason' => 'daemon_state_failed',
                'message' => sprintf('Wings reported server state `%s`.', $state),
                'meta' => [
                    'probe' => 'wings_server',
                    'server_uuid' => $server->uuid,
                    'state' => $state,
                ],
                'summary' => sprintf(
                    'Server `%s` (`%s`) is down because Wings reported state `%s`.',
                    $server->name,
                    $server->uuidShort,
                    $state
                ),
            ];
        }

        $this->autoRestart->clearStopIntentIfRunning($server);

        $host = $this->resolveProbeHost($server);
        $port = (int) ($server->allocation?->port ?? 0);
        if ($host === null || $port < 1) {
            return [
                'status' => DownDetectorMonitor::STATUS_DOWN,
                'reason' => 'port_probe_failed',
                'message' => 'The primary allocation is missing or invalid for TCP probing.',
                'meta' => [
                    'probe' => 'tcp_port',
                ],
                'summary' => sprintf('Server `%s` (`%s`) is down because its primary allocation is invalid.', $server->name, $server->uuidShort),
            ];
        }

        try {
            $this->probe->probeTcpPort($host, $port, $timeoutMs);
        } catch (Throwable $exception) {
            if ($ignored = $this->autoRestart->ignoredObservationForPowerIntent($server, $latestPowerEvent, $now, $intervalSeconds)) {
                return $ignored;
            }

            return [
                'status' => DownDetectorMonitor::STATUS_DOWN,
                'reason' => 'port_probe_failed',
                'message' => $exception->getMessage(),
                'meta' => [
                    'probe' => 'tcp_port',
                    'host' => $host,
                    'port' => $port,
                ],
                'summary' => sprintf(
                    'Server `%s` (`%s`) is down because TCP probe to `%s:%d` failed: %s',
                    $server->name,
                    $server->uuidShort,
                    $host,
                    $port,
                    $exception->getMessage()
                ),
            ];
        }

        return [
            'status' => DownDetectorMonitor::STATUS_UP,
            'reason' => null,
            'message' => sprintf('Wings is healthy and TCP probe to %s:%d succeeded.', $host, $port),
            'meta' => [
                'probe' => 'tcp_port',
                'host' => $host,
                'port' => $port,
                'state' => $state,
            ],
            'summary' => sprintf('Server `%s` (`%s`) recovered and is responding on `%s:%d`.', $server->name, $server->uuidShort, $host, $port),
        ];
    }

    private function applyObservation(
        DownDetectorMonitor $monitor,
        array $observation,
        CarbonImmutable $checkedAt,
        int $failureThreshold,
        int $recoveryThreshold,
        array &$changes
    ): string {
        $observedStatus = (string) $observation['status'];
        $previousStatus = (string) ($monitor->current_status ?: DownDetectorMonitor::STATUS_UNKNOWN);

        if (!$monitor->exists) {
            $this->baselineMonitor($monitor, $observedStatus, $observation, $checkedAt);

            return $monitor->current_status;
        }

        $monitor->last_checked_at = $checkedAt;
        $monitor->last_reason = $observation['reason'];
        $monitor->last_message = $observation['message'];
        $monitor->last_meta = $observation['meta'];

        if ($observedStatus === DownDetectorMonitor::STATUS_IGNORED) {
            $monitor->consecutive_failures = 0;
            $monitor->consecutive_successes = 0;
            if ($previousStatus !== DownDetectorMonitor::STATUS_IGNORED) {
                $monitor->current_status = DownDetectorMonitor::STATUS_IGNORED;
                $monitor->last_status_changed_at = $checkedAt;
            }
            $monitor->save();

            return $monitor->current_status;
        }

        if ($observedStatus === DownDetectorMonitor::STATUS_UP) {
            $monitor->consecutive_failures = 0;
            $monitor->consecutive_successes = in_array($previousStatus, [DownDetectorMonitor::STATUS_UP, DownDetectorMonitor::STATUS_DOWN], true)
                ? $monitor->consecutive_successes + 1
                : 1;

            if ($previousStatus === DownDetectorMonitor::STATUS_DOWN && $monitor->consecutive_successes >= $recoveryThreshold) {
                $this->transitionMonitor($monitor, $previousStatus, DownDetectorMonitor::STATUS_UP, $observation, $checkedAt, $changes['recovered']);
            } elseif (in_array($previousStatus, [DownDetectorMonitor::STATUS_UNKNOWN, DownDetectorMonitor::STATUS_IGNORED], true)
                && $monitor->consecutive_successes >= $recoveryThreshold) {
                $monitor->current_status = DownDetectorMonitor::STATUS_UP;
                $monitor->last_status_changed_at = $checkedAt;
            }

            $monitor->save();

            return $monitor->current_status;
        }

        $monitor->consecutive_successes = 0;
        $monitor->consecutive_failures = $previousStatus === DownDetectorMonitor::STATUS_DOWN
            ? $monitor->consecutive_failures + 1
            : 1;

        if ($previousStatus === DownDetectorMonitor::STATUS_UP && $monitor->consecutive_failures >= $failureThreshold) {
            $this->transitionMonitor($monitor, $previousStatus, DownDetectorMonitor::STATUS_DOWN, $observation, $checkedAt, $changes['down']);
        } elseif ($previousStatus === DownDetectorMonitor::STATUS_IGNORED && $monitor->consecutive_failures >= $failureThreshold) {
            $this->transitionMonitor($monitor, $previousStatus, DownDetectorMonitor::STATUS_DOWN, $observation, $checkedAt, $changes['down']);
        } elseif ($previousStatus === DownDetectorMonitor::STATUS_UNKNOWN && $monitor->consecutive_failures >= $failureThreshold) {
            $monitor->current_status = DownDetectorMonitor::STATUS_DOWN;
            $monitor->last_status_changed_at = $checkedAt;
        }

        $monitor->save();

        return $monitor->current_status;
    }

    private function baselineMonitor(
        DownDetectorMonitor $monitor,
        string $status,
        array $observation,
        CarbonImmutable $checkedAt
    ): void {
        $monitor->current_status = $status;
        $monitor->last_reason = $observation['reason'];
        $monitor->last_message = $observation['message'];
        $monitor->last_meta = $observation['meta'];
        $monitor->last_checked_at = $checkedAt;
        $monitor->last_status_changed_at = $checkedAt;
        $monitor->consecutive_failures = $status === DownDetectorMonitor::STATUS_DOWN ? 1 : 0;
        $monitor->consecutive_successes = $status === DownDetectorMonitor::STATUS_UP ? 1 : 0;
        $monitor->save();
    }

    private function transitionMonitor(
        DownDetectorMonitor $monitor,
        string $fromStatus,
        string $toStatus,
        array $observation,
        CarbonImmutable $checkedAt,
        array &$changeBucket
    ): void {
        $monitor->current_status = $toStatus;
        $monitor->last_status_changed_at = $checkedAt;

        $incident = DownDetectorIncident::query()->create([
            'monitor_id' => $monitor->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'reason' => $observation['reason'],
            'summary' => $observation['summary'],
            'meta' => $observation['meta'],
            'created_at' => $checkedAt,
        ]);

        $changeBucket[] = [
            'summary' => $observation['summary'],
            'reason' => $observation['reason'],
        ];

        if (
            $toStatus === DownDetectorMonitor::STATUS_DOWN
            && $monitor->monitorable_type === Server::class
        ) {
            $server = Server::query()->with(['node', 'allocation', 'transfer'])->find((int) $monitor->monitorable_id);

            if ($server) {
                $this->autoRestart->handleDownTransition($server, $monitor, $incident, $observation, $checkedAt);
            }
        }
    }

    private function latestPowerEvents(Collection $servers): array
    {
        $serverIds = $servers->pluck('id')->all();
        if ($serverIds === []) {
            return [];
        }

        $rows = ActivityLogSubject::query()
            ->select([
                'activity_log_subjects.subject_id as server_id',
                'activity_logs.event',
                'activity_logs.timestamp',
            ])
            ->join('activity_logs', 'activity_logs.id', '=', 'activity_log_subjects.activity_log_id')
            ->where('activity_log_subjects.subject_type', Server::class)
            ->whereIn('activity_log_subjects.subject_id', $serverIds)
            ->whereIn('activity_logs.event', self::POWER_EVENTS)
            ->orderByDesc('activity_logs.timestamp')
            ->get();

        $latest = [];
        foreach ($rows as $row) {
            $serverId = (int) $row->server_id;
            if (isset($latest[$serverId])) {
                continue;
            }

            $latest[$serverId] = [
                'event' => (string) $row->event,
                'timestamp' => CarbonImmutable::parse($row->timestamp),
            ];
        }

        return $latest;
    }

    private function isIntentionallyStopped(
        ?array $latestPowerEvent,
        DownDetectorMonitor $monitor,
        CarbonImmutable $now,
        int $intervalSeconds
    ): bool
    {
        if (!$latestPowerEvent) {
            return false;
        }

        if (!in_array((string) ($latestPowerEvent['event'] ?? ''), ['server:power.stop', 'server:power.kill'], true)) {
            return false;
        }

        if ($monitor->current_status === DownDetectorMonitor::STATUS_IGNORED) {
            return true;
        }

        $graceWindow = max($intervalSeconds * 3, 300);
        $timestamp = $latestPowerEvent['timestamp'] ?? null;
        if (!$timestamp instanceof CarbonImmutable) {
            return false;
        }

        return $timestamp->greaterThanOrEqualTo($now->subSeconds($graceWindow));
    }

    private function ignoredObservation(string $reason, string $summary): array
    {
        return [
            'status' => DownDetectorMonitor::STATUS_IGNORED,
            'reason' => $reason,
            'message' => $summary,
            'meta' => ['probe' => 'ignored'],
            'summary' => $summary,
        ];
    }

    private function sendNodePeriodicSummaryIfDue(array $config, array $summary, CarbonImmutable $now, bool $force): void
    {
        if (!data_get($config, 'node.periodic_reports_enabled', false)) {
            return;
        }

        $lastSentAt = data_get($config, 'node.last_periodic_report_at');
        $intervalMinutes = max((int) data_get($config, 'node.periodic_report_interval_minutes', 1440), 60);

        if (!$force && $lastSentAt) {
            $lastSent = CarbonImmutable::parse((string) $lastSentAt);
            if ($lastSent->diffInMinutes($now) < $intervalMinutes) {
                return;
            }
        }

        $activeOutages = DownDetectorMonitor::query()
            ->with('monitorable')
            ->where('monitorable_type', Node::class)
            ->where('current_status', DownDetectorMonitor::STATUS_DOWN)
            ->orderByDesc('last_status_changed_at')
            ->limit(25)
            ->get()
            ->map(fn (DownDetectorMonitor $monitor) => [
                'name' => $monitor->monitorable instanceof Node
                    ? sprintf('%s (%s)', $monitor->monitorable->name, $monitor->monitorable->getConnectionAddress())
                    : sprintf('Deleted node #%d', (int) $monitor->monitorable_id),
                'reason' => $monitor->last_reason,
                'message' => $monitor->last_message,
            ]);

        $this->discord->sendSnapshotReport('node', $summary, $activeOutages, $now);
        $this->settings->saveNodePeriodicReportTimestamp($now);
    }

    private function resolveProbeHost(Server $server): ?string
    {
        $alias = trim((string) ($server->allocation?->alias ?? ''));
        $ip = trim((string) ($server->allocation?->ip ?? ''));

        if ($alias !== '' && !$this->isWildcardHost($alias)) {
            return $alias;
        }

        if ($ip !== '' && !$this->isWildcardHost($ip)) {
            return $ip;
        }

        return null;
    }

    private function isWildcardHost(string $host): bool
    {
        return in_array(trim($host), ['0.0.0.0', '::', '[::]'], true);
    }

    private function monitorFor(Collection $monitors, string $type, int $id): DownDetectorMonitor
    {
        $key = $this->monitorKey($type, $id);
        if ($monitors->has($key)) {
            return $monitors->get($key);
        }

        $monitor = new DownDetectorMonitor([
            'current_status' => DownDetectorMonitor::STATUS_UNKNOWN,
        ]);
        $monitor->monitorable_type = $type;
        $monitor->monitorable_id = $id;

        $monitors->put($key, $monitor);

        return $monitor;
    }

    private function monitorKey(string $type, int $id): string
    {
        return $type . ':' . $id;
    }

    private function emptySummaryBucket(): array
    {
        return [
            'checked' => 0,
            'up' => 0,
            'down' => 0,
            'ignored' => 0,
            'unknown' => 0,
        ];
    }

    private function incrementSummaryStatus(array &$bucket, string $status): void
    {
        $bucket[$status] = ($bucket[$status] ?? 0) + 1;
    }
}
