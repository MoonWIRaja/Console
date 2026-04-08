<?php

namespace Pterodactyl\Http\Controllers\Admin\DownDetector;

use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Prologue\Alerts\AlertsMessageBag;
use Throwable;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Http\Requests\Admin\DownDetector\UpdateDownDetectorNodeSettingsRequest;
use Pterodactyl\Http\Requests\Admin\DownDetector\UpdateDownDetectorServerSettingsRequest;
use Pterodactyl\Http\Requests\Admin\DownDetector\UpdateDownDetectorSettingsRequest;
use Pterodactyl\Models\DownDetector\DownDetectorIncident;
use Pterodactyl\Models\DownDetector\DownDetectorMonitor;
use Pterodactyl\Models\Node;
use Pterodactyl\Models\Server;
use Pterodactyl\Services\DownDetector\DownDetectorDiscordService;
use Pterodactyl\Services\DownDetector\DownDetectorRunnerService;
use Pterodactyl\Services\DownDetector\DownDetectorSettingsService;

class DownDetectorController extends Controller
{
    public function __construct(
        private AlertsMessageBag $alert,
        private DownDetectorSettingsService $settings,
        private DownDetectorRunnerService $runner,
        private DownDetectorDiscordService $discord,
    ) {
    }

    public function index(Request $request): View
    {
        $config = $this->settings->config();
        $selectedTab = in_array((string) $request->query('tab', 'nodes'), ['nodes', 'servers'], true)
            ? (string) $request->query('tab', 'nodes')
            : 'nodes';

        $downMonitors = DownDetectorMonitor::query()
            ->with('monitorable')
            ->where('current_status', DownDetectorMonitor::STATUS_DOWN)
            ->orderByDesc('last_status_changed_at')
            ->limit(100)
            ->get();

        $recentIncidents = DownDetectorIncident::query()
            ->with('monitor.monitorable')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return view('admin.down-detector.index', [
            'config' => $config,
            'meta' => [
                'discord_bot_configured' => filled(config('services.discord.bot_token')),
                'node_alert_ready' => filled(config('services.discord.bot_token')) && filled(data_get($config, 'node.discord.alert_channel_id')),
                'server_alert_ready' => filled(config('services.discord.bot_token')) && filled(data_get($config, 'server.discord.alert_channel_id')),
                'server_launcher_ready' => filled(config('services.discord.bot_token'))
                    && filled(data_get($config, 'server.discord.launcher_channel_id'))
                    && filled(data_get($config, 'server.discord.launcher_message_id')),
                'server_auto_restart_default_enabled' => (bool) data_get($config, 'server.auto_restart_default_enabled', false),
                'last_run_at_human' => $config['last_run_at']
                    ? CarbonImmutable::parse($config['last_run_at'])->format('Y-m-d H:i:s T')
                    : 'Never',
                'node_last_periodic_report_at_human' => data_get($config, 'node.last_periodic_report_at')
                    ? CarbonImmutable::parse((string) data_get($config, 'node.last_periodic_report_at'))->format('Y-m-d H:i:s T')
                    : 'Never',
                'selected_tab' => $selectedTab,
            ],
            'summary' => [
                'nodes' => $this->summarize(Node::class),
                'servers' => $this->summarize(Server::class),
            ],
            'downMonitors' => [
                'nodes' => $downMonitors
                    ->where('monitorable_type', Node::class)
                    ->map(fn (DownDetectorMonitor $monitor) => $this->transformMonitor($monitor))
                    ->values(),
                'servers' => $downMonitors
                    ->where('monitorable_type', Server::class)
                    ->map(fn (DownDetectorMonitor $monitor) => $this->transformMonitor($monitor))
                    ->values(),
            ],
            'recentIncidents' => [
                'nodes' => $recentIncidents
                    ->filter(fn (DownDetectorIncident $incident) => $incident->monitor?->monitorable_type === Node::class)
                    ->map(fn (DownDetectorIncident $incident) => $this->transformIncident($incident))
                    ->values(),
                'servers' => $recentIncidents
                    ->filter(fn (DownDetectorIncident $incident) => $incident->monitor?->monitorable_type === Server::class)
                    ->map(fn (DownDetectorIncident $incident) => $this->transformIncident($incident))
                    ->values(),
            ],
        ]);
    }

    public function update(UpdateDownDetectorSettingsRequest $request): RedirectResponse
    {
        $this->settings->saveCore($request->validated());

        $this->alert->success('Down detector runtime settings updated successfully.')->flash();

        return redirect()->route('admin.down-detector', ['tab' => $request->input('tab', 'nodes')]);
    }

    public function updateNodes(UpdateDownDetectorNodeSettingsRequest $request): RedirectResponse
    {
        $this->settings->saveNode($request->validated());

        $this->alert->success('Node detector settings updated successfully.')->flash();

        return redirect()->route('admin.down-detector', ['tab' => 'nodes']);
    }

    public function updateServers(UpdateDownDetectorServerSettingsRequest $request): RedirectResponse
    {
        $this->settings->saveServer($request->validated());

        $this->alert->success('Server detector settings updated successfully.')->flash();

        return redirect()->route('admin.down-detector', ['tab' => 'servers']);
    }

    public function check(string $scope): RedirectResponse
    {
        abort_unless(in_array($scope, ['node', 'server'], true), 404);

        try {
            $result = $this->runner->run(true, true);
            $checkedAt = isset($result['checked_at']) ? CarbonImmutable::parse($result['checked_at']) : CarbonImmutable::now();

            $activeOutages = DownDetectorMonitor::query()
                ->with('monitorable')
                ->where('monitorable_type', $scope === 'node' ? Node::class : Server::class)
                ->where('current_status', DownDetectorMonitor::STATUS_DOWN)
                ->orderByDesc('last_status_changed_at')
                ->limit(25)
                ->get()
                ->map(fn (DownDetectorMonitor $monitor) => $this->transformMonitor($monitor));

            $this->discord->sendSnapshotReport($scope, $result, $activeOutages, $checkedAt);

            $this->alert->success(sprintf(
                'Manual %s detector check completed and the snapshot report was sent to Discord.',
                $scope
            ))->flash();
        } catch (Throwable $exception) {
            report($exception);
            $this->alert->danger($exception->getMessage())->flash();
        }

        return redirect()->route('admin.down-detector', ['tab' => $scope === 'node' ? 'nodes' : 'servers']);
    }

    public function syncServerLauncher(): RedirectResponse
    {
        try {
            $response = $this->discord->syncServerLauncherMessage();
            $this->settings->saveServerLauncherMessageId((string) data_get($response, 'id'));

            $this->alert->success('The server health launcher was synced to Discord successfully.')->flash();
        } catch (Throwable $exception) {
            report($exception);
            $this->alert->danger($exception->getMessage())->flash();
        }

        return redirect()->route('admin.down-detector', ['tab' => 'servers']);
    }

    private function summarize(string $type): array
    {
        $counts = DownDetectorMonitor::query()
            ->where('monitorable_type', $type)
            ->selectRaw('current_status, COUNT(*) as aggregate')
            ->groupBy('current_status')
            ->pluck('aggregate', 'current_status');

        return [
            'total' => (int) $counts->sum(),
            'up' => (int) ($counts[DownDetectorMonitor::STATUS_UP] ?? 0),
            'down' => (int) ($counts[DownDetectorMonitor::STATUS_DOWN] ?? 0),
            'ignored' => (int) ($counts[DownDetectorMonitor::STATUS_IGNORED] ?? 0),
            'unknown' => (int) ($counts[DownDetectorMonitor::STATUS_UNKNOWN] ?? 0),
        ];
    }

    private function transformMonitor(DownDetectorMonitor $monitor): array
    {
        $subject = $monitor->monitorable;
        $type = $monitor->type_label;

        return [
            'type' => $type,
            'name' => match (true) {
                $subject instanceof Node => sprintf('%s (%s)', $subject->name, $subject->getConnectionAddress()),
                $subject instanceof Server => sprintf('%s (%s)', $subject->name, $subject->uuidShort),
                default => sprintf('Deleted %s #%d', $type, (int) $monitor->monitorable_id),
            },
            'status' => $monitor->current_status,
            'reason' => $monitor->last_reason,
            'message' => $monitor->last_message,
            'checked_at' => optional($monitor->last_checked_at)?->format('Y-m-d H:i:s T') ?? 'Never',
            'changed_at' => optional($monitor->last_status_changed_at)?->format('Y-m-d H:i:s T') ?? 'Never',
        ];
    }

    private function transformIncident(DownDetectorIncident $incident): array
    {
        $monitor = $incident->monitor;
        $subject = $monitor?->monitorable;
        $type = $monitor?->type_label ?? 'unknown';

        return [
            'type' => $type,
            'name' => match (true) {
                $subject instanceof Node => sprintf('%s (%s)', $subject->name, $subject->getConnectionAddress()),
                $subject instanceof Server => sprintf('%s (%s)', $subject->name, $subject->uuidShort),
                default => sprintf('Deleted %s #%d', $type, (int) ($monitor?->monitorable_id ?? 0)),
            },
            'from_status' => $incident->from_status ?? 'n/a',
            'to_status' => $incident->to_status,
            'reason' => $incident->reason,
            'summary' => $incident->summary,
            'created_at' => optional($incident->created_at)?->format('Y-m-d H:i:s T') ?? 'Never',
        ];
    }
}
