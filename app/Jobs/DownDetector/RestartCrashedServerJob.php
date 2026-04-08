<?php

namespace Pterodactyl\Jobs\DownDetector;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Models\DownDetector\DownDetectorIncident;
use Pterodactyl\Models\DownDetector\DownDetectorMonitor;
use Pterodactyl\Models\Server;
use Pterodactyl\Repositories\Wings\DaemonPowerRepository;
use Pterodactyl\Services\DownDetector\DownDetectorAutoRestartService;
use Pterodactyl\Services\DownDetector\DownDetectorRunnerService;
use Throwable;

class RestartCrashedServerJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public int $serverId,
        public int $monitorId,
        public int $incidentId,
        public string $detectedAt,
    ) {
    }

    public function handle(
        DownDetectorRunnerService $runner,
        DownDetectorAutoRestartService $autoRestart,
        DaemonPowerRepository $powerRepository,
    ): void {
        $server = Server::query()->with(['node', 'allocation', 'transfer'])->find($this->serverId);
        $monitor = DownDetectorMonitor::query()->find($this->monitorId);
        $incident = DownDetectorIncident::query()->find($this->incidentId);

        if (!$server || !$monitor || !$incident) {
            $autoRestart->clearPendingRestart($this->serverId);

            return;
        }

        try {
            if (!$server->auto_restart_on_crash) {
                $autoRestart->markIncidentOutcome($incident, 'disabled', [
                    'message' => 'Server auto restart is disabled.',
                ]);

                Activity::event('server:auto_restart.skipped')
                    ->subject($server)
                    ->property([
                        'reason' => 'disabled',
                        'incident_id' => $incident->id,
                    ])
                    ->log();

                return;
            }

            if ($monitor->current_status !== DownDetectorMonitor::STATUS_DOWN) {
                $autoRestart->markIncidentOutcome($incident, 'skipped_already_running', [
                    'message' => 'Monitor status no longer reports this server as down.',
                ]);

                Activity::event('server:auto_restart.skipped')
                    ->subject($server)
                    ->property([
                        'reason' => 'skipped_already_running',
                        'incident_id' => $incident->id,
                    ])
                    ->log();

                return;
            }

            $observation = $runner->inspectServer($server);
            if (($observation['status'] ?? null) !== DownDetectorMonitor::STATUS_DOWN) {
                $reason = (string) ($observation['reason'] ?? 'skipped_already_running');
                $mappedReason = in_array($reason, ['manual_stop', 'manual_kill'], true)
                    ? 'skipped_manual_stop'
                    : ($reason === 'starting' ? 'skipped_recovering' : 'skipped_already_running');

                $autoRestart->markIncidentOutcome($incident, $mappedReason, [
                    'message' => (string) ($observation['message'] ?? 'Server is no longer eligible for auto restart.'),
                    'observation' => $observation,
                ]);

                Activity::event('server:auto_restart.skipped')
                    ->subject($server)
                    ->property([
                        'reason' => $mappedReason,
                        'incident_id' => $incident->id,
                    ])
                    ->log();

                return;
            }

            if ($autoRestart->hasExceededAttemptLimit($server)) {
                $autoRestart->markIncidentOutcome($incident, 'skipped_limit_reached', [
                    'message' => 'The auto restart retry window has been exhausted for this server.',
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

            $autoRestart->recordRestartAttempt($server);
            $powerRepository->setServer($server)->send('start');

            $autoRestart->markIncidentOutcome($incident, 'restarted', [
                'executed_at' => now()->toIso8601String(),
                'message' => 'A delayed crash recovery restart was sent to Wings.',
            ]);

            Activity::event('server:auto_restart.executed')
                ->subject($server)
                ->property([
                    'incident_id' => $incident->id,
                    'server_id' => $server->id,
                ])
                ->log();
        } catch (Throwable $exception) {
            report($exception);

            $autoRestart->markIncidentOutcome($incident, 'failed_to_restart', [
                'message' => $exception->getMessage(),
            ]);

            Activity::event('server:auto_restart.skipped')
                ->subject($server)
                ->property([
                    'reason' => 'failed_to_restart',
                    'incident_id' => $incident->id,
                    'message' => $exception->getMessage(),
                ])
                ->log();
        } finally {
            $autoRestart->clearPendingRestart($this->serverId);
        }
    }
}
