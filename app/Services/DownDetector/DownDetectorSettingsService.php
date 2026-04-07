<?php

namespace Pterodactyl\Services\DownDetector;

use Carbon\CarbonInterface;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;

class DownDetectorSettingsService
{
    public function __construct(private SettingsRepositoryInterface $settings)
    {
    }

    public function config(): array
    {
        $legacyChannel = $this->normalizedString(config('down_detector.discord.channel_id'));

        return [
            'enabled' => (bool) config('down_detector.enabled', false),
            'discord' => [
                'channel_id' => $legacyChannel,
            ],
            'monitor_nodes' => (bool) config('down_detector.monitor_nodes', true),
            'monitor_servers' => (bool) config('down_detector.monitor_servers', true),
            'interval_seconds' => max((int) config('down_detector.interval_seconds', 60), 60),
            'probe_timeout_ms' => max((int) config('down_detector.probe_timeout_ms', 3000), 1000),
            'failure_threshold' => max((int) config('down_detector.failure_threshold', 2), 1),
            'recovery_threshold' => max((int) config('down_detector.recovery_threshold', 2), 1),
            'node' => [
                'discord' => [
                    'alert_channel_id' => $this->normalizedString(config('down_detector.node.discord.alert_channel_id')) ?? $legacyChannel,
                ],
                'periodic_reports_enabled' => (bool) config('down_detector.node.periodic_reports_enabled', false),
                'periodic_report_interval_minutes' => max((int) config('down_detector.node.periodic_report_interval_minutes', 1440), 60),
                'last_periodic_report_at' => $this->normalizedString(config('down_detector.node.last_periodic_report_at')),
            ],
            'server' => [
                'discord' => [
                    'alert_channel_id' => $this->normalizedString(config('down_detector.server.discord.alert_channel_id')) ?? $legacyChannel,
                    'launcher_channel_id' => $this->normalizedString(config('down_detector.server.discord.launcher_channel_id')),
                    'launcher_message_id' => $this->normalizedString(config('down_detector.server.discord.launcher_message_id')),
                ],
            ],
            'last_run_at' => $this->normalizedString(config('down_detector.last_run_at')),
            'last_run_summary' => is_array(config('down_detector.last_run_summary', []))
                ? config('down_detector.last_run_summary', [])
                : [],
        ];
    }

    public function saveCore(array $input): void
    {
        $payload = [
            'down_detector:enabled' => (bool) data_get($input, 'enabled', false),
            'down_detector:interval_seconds' => max((int) data_get($input, 'interval_seconds', 60), 60),
            'down_detector:probe_timeout_ms' => max((int) data_get($input, 'probe_timeout_ms', 3000), 1000),
            'down_detector:failure_threshold' => max((int) data_get($input, 'failure_threshold', 2), 1),
            'down_detector:recovery_threshold' => max((int) data_get($input, 'recovery_threshold', 2), 1),
        ];

        foreach ($payload as $key => $value) {
            $this->writeSetting($key, $value);
        }
    }

    public function saveNode(array $input): void
    {
        $payload = [
            'down_detector:monitor_nodes' => (bool) data_get($input, 'monitor_nodes', true),
            'down_detector:node:discord:alert_channel_id' => $this->normalizedString(data_get($input, 'node.discord.alert_channel_id')),
            'down_detector:node:periodic_reports_enabled' => (bool) data_get($input, 'node.periodic_reports_enabled', false),
            'down_detector:node:periodic_report_interval_minutes' => max((int) data_get($input, 'node.periodic_report_interval_minutes', 1440), 60),
        ];

        foreach ($payload as $key => $value) {
            $this->writeSetting($key, $value);
        }
    }

    public function saveServer(array $input): void
    {
        $payload = [
            'down_detector:monitor_servers' => (bool) data_get($input, 'monitor_servers', true),
            'down_detector:server:discord:alert_channel_id' => $this->normalizedString(data_get($input, 'server.discord.alert_channel_id')),
            'down_detector:server:discord:launcher_channel_id' => $this->normalizedString(data_get($input, 'server.discord.launcher_channel_id')),
        ];

        foreach ($payload as $key => $value) {
            $this->writeSetting($key, $value);
        }
    }

    public function saveRuntimeSummary(CarbonInterface $checkedAt, array $summary): void
    {
        $this->writeSetting('down_detector:last_run_at', $checkedAt->toIso8601String());
        $this->writeSetting('down_detector:last_run_summary', $summary);
    }

    public function saveNodePeriodicReportTimestamp(CarbonInterface $sentAt): void
    {
        $this->writeSetting('down_detector:node:last_periodic_report_at', $sentAt->toIso8601String());
    }

    public function saveServerLauncherMessageId(?string $messageId): void
    {
        $this->writeSetting('down_detector:server:discord:launcher_message_id', $this->normalizedString($messageId));
    }

    private function writeSetting(string $key, mixed $value): void
    {
        $stored = match (true) {
            is_bool($value) => $value ? 'true' : 'false',
            is_array($value) => json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            is_null($value) => null,
            default => (string) $value,
        };

        $this->settings->set('settings::' . $key, $stored);
        config()->set(str_replace(':', '.', $key), $value);
    }

    private function normalizedString(mixed $value): ?string
    {
        $string = trim((string) ($value ?? ''));

        return $string === '' ? null : $string;
    }
}
