<?php

namespace Pterodactyl\Services\DownDetector;

use Carbon\CarbonInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Pterodactyl\Models\DownDetector\DownDetectorMonitor;
use Pterodactyl\Models\Node;
use RuntimeException;

class DownDetectorDiscordService
{
    private const API_BASE = 'https://discord.com/api/v10';

    public function sendDigest(string $scope, array $changes, CarbonInterface $checkedAt): void
    {
        if (($changes['down'] ?? []) === [] && ($changes['recovered'] ?? []) === []) {
            return;
        }

        $channelId = $this->channelIdForScope($scope);
        if (!$channelId) {
            return;
        }

        if ($scope === 'node') {
            $this->postPayload($channelId, $this->buildNodeDigestPayload($changes, $checkedAt));

            return;
        }

        $content = $this->buildDigestContent($scope, $changes, $checkedAt);
        if ($content === '') {
            return;
        }

        $this->postMessage($channelId, $content);
    }

    public function sendSnapshotReport(string $scope, array $summary, iterable $activeOutages, CarbonInterface $checkedAt): void
    {
        $channelId = $this->channelIdForScope($scope);
        if (!$channelId) {
            throw new RuntimeException(sprintf(
                'Set the %s Discord alert channel ID before sending a test report.',
                $this->scopeLabel($scope)
            ));
        }

        if ($scope === 'node') {
            $this->postPayload($channelId, $this->buildNodeSnapshotPayload($summary, $activeOutages, $checkedAt));

            return;
        }

        $content = $this->buildSnapshotContent($scope, $summary, $activeOutages, $checkedAt);
        $this->postMessage($channelId, $content);
    }

    public function syncServerLauncherMessage(): array
    {
        $channelId = $this->serverLauncherChannelId();
        if (!$channelId) {
            throw new RuntimeException('Set the Server Status Launcher Channel ID before syncing the Discord launcher.');
        }

        $payload = $this->serverLauncherPayload();
        $messageId = trim((string) config('down_detector.server.discord.launcher_message_id', ''));

        $response = null;
        if ($messageId !== '') {
            $response = $this->botHttp()->patch(
                sprintf('%s/channels/%s/messages/%s', self::API_BASE, $channelId, $messageId),
                $payload
            );

            if ($response->status() === 404 && str_contains(strtolower($this->discordErrorMessage($response, '')), 'unknown message')) {
                $response = null;
            }
        }

        if (!$response) {
            $response = $this->botHttp()->post(
                sprintf('%s/channels/%s/messages', self::API_BASE, $channelId),
                $payload
            );
        }

        if (!$response->successful()) {
            throw new RuntimeException($this->discordErrorMessage($response, 'Unable to sync the server status launcher message.'));
        }

        $payload = $response->json();

        return is_array($payload) ? $payload : [];
    }

    public function serverLauncherPayload(): array
    {
        return [
            'content' => null,
            'embeds' => [[
                'title' => sprintf('%s Server Health', config('app.name', 'Panel')),
                'description' => "Check the live health of one of your linked servers.\n\nThe bot only shows the result to you and hides it after about one minute.",
                'color' => 0x6CC24A,
                'footer' => [
                    'text' => 'Linked Discord account required before server health can be viewed.',
                ],
            ]],
            'components' => [[
                'type' => 1,
                'components' => [
                    [
                        'type' => 2,
                        'style' => 1,
                        'label' => 'Check My Server',
                        'custom_id' => 'down-detector:server:launcher',
                    ],
                    [
                        'type' => 2,
                        'style' => 5,
                        'label' => 'Open Panel',
                        'url' => url('/'),
                    ],
                ],
            ]],
            'allowed_mentions' => ['parse' => []],
        ];
    }

    private function buildDigestContent(string $scope, array $changes, CarbonInterface $checkedAt): string
    {
        $lines = [
            sprintf('**%s %s Alerts**', config('app.name', 'Panel'), $this->scopeLabel($scope)),
            sprintf('Checked at `%s`', $checkedAt->format('Y-m-d H:i:s T')),
            '',
        ];

        if (($changes['down'] ?? []) !== []) {
            $lines[] = '**Down**';
            foreach ($changes['down'] as $change) {
                $lines[] = sprintf('- %s', $change['summary']);
            }
            $lines[] = '';
        }

        if (($changes['recovered'] ?? []) !== []) {
            $lines[] = '**Recovered**';
            foreach ($changes['recovered'] as $change) {
                $lines[] = sprintf('- %s', $change['summary']);
            }
        }

        $content = trim(implode("\n", $lines));

        return mb_strlen($content) <= 1900
            ? $content
            : rtrim(Str::limit($content, 1900, '')) . "\n\n[Digest truncated]";
    }

    private function buildNodeDigestPayload(array $changes, CarbonInterface $checkedAt): array
    {
        $down = array_values((array) ($changes['down'] ?? []));
        $recovered = array_values((array) ($changes['recovered'] ?? []));

        $fields = [];

        if ($down !== []) {
            $fields[] = [
                'name' => sprintf('Down (%d)', count($down)),
                'value' => $this->embedBulletList($down, fn (array $change) => (string) ($change['summary'] ?? 'Unknown outage')),
                'inline' => false,
            ];
        }

        if ($recovered !== []) {
            $fields[] = [
                'name' => sprintf('Recovered (%d)', count($recovered)),
                'value' => $this->embedBulletList($recovered, fn (array $change) => (string) ($change['summary'] ?? 'Unknown recovery')),
                'inline' => false,
            ];
        }

        return [
            'content' => null,
            'embeds' => [[
                'title' => sprintf('%s Node Alert Digest', config('app.name', 'Panel')),
                'description' => 'Public node health transitions from the latest detector cycle.',
                'color' => $down !== [] ? 0xE45757 : 0x6CC24A,
                'fields' => $fields,
                'footer' => [
                    'text' => 'Visible to everyone in this channel',
                ],
                'timestamp' => $checkedAt->toIso8601String(),
            ]],
            'allowed_mentions' => ['parse' => []],
        ];
    }

    private function buildSnapshotContent(string $scope, array $summary, iterable $activeOutages, CarbonInterface $checkedAt): string
    {
        $bucket = $scope === 'node'
            ? (array) data_get($summary, 'nodes', [])
            : (array) data_get($summary, 'servers', []);

        $outageLines = [];
        foreach ($activeOutages as $outage) {
            $name = trim((string) data_get($outage, 'name', 'Unknown target'));
            $reason = trim((string) data_get($outage, 'reason', 'n/a'));
            $message = trim((string) data_get($outage, 'message', ''));
            $outageLines[] = sprintf('- `%s` [%s]%s', $name, $reason, $message !== '' ? ' ' . $message : '');
        }

        $lines = [
            sprintf('**%s %s Manual Check**', config('app.name', 'Panel'), $this->scopeLabel($scope)),
            sprintf('Checked at `%s`', $checkedAt->format('Y-m-d H:i:s T')),
            '',
        ];

        if ($scope === 'node') {
            $lines[] = sprintf(
                'Nodes: checked `%d`, up `%d`, down `%d`, unknown `%d`',
                (int) data_get($bucket, 'checked', 0),
                (int) data_get($bucket, 'up', 0),
                (int) data_get($bucket, 'down', 0),
                (int) data_get($bucket, 'unknown', 0)
            );
        } else {
            $lines[] = sprintf(
                'Servers: checked `%d`, up `%d`, down `%d`, ignored `%d`, unknown `%d`',
                (int) data_get($bucket, 'checked', 0),
                (int) data_get($bucket, 'up', 0),
                (int) data_get($bucket, 'down', 0),
                (int) data_get($bucket, 'ignored', 0),
                (int) data_get($bucket, 'unknown', 0)
            );
        }

        $lines[] = '';
        $lines[] = '**Active Outages**';

        if ($outageLines === []) {
            $lines[] = '- None';
        } else {
            $lines = array_merge($lines, array_slice($outageLines, 0, 10));
            if (count($outageLines) > 10) {
                $lines[] = sprintf('- ...and %d more', count($outageLines) - 10);
            }
        }

        $content = trim(implode("\n", $lines));

        return mb_strlen($content) <= 1900
            ? $content
            : rtrim(Str::limit($content, 1900, '')) . "\n\n[Snapshot truncated]";
    }

    private function buildNodeSnapshotPayload(array $summary, iterable $activeOutages, CarbonInterface $checkedAt): array
    {
        $bucket = (array) data_get($summary, 'nodes', []);
        $nodeRows = $this->nodeSnapshotRows();
        $outages = [];

        foreach ($activeOutages as $outage) {
            $name = trim((string) data_get($outage, 'name', 'Unknown node'));
            $reason = trim((string) data_get($outage, 'reason', 'n/a'));
            $message = trim((string) data_get($outage, 'message', ''));

            $outages[] = trim(sprintf(
                '**%s**\n`%s`%s',
                $name,
                $reason,
                $message !== '' ? "\n" . $message : ''
            ));
        }

        $downCount = (int) data_get($bucket, 'down', 0);
        $unknownCount = (int) data_get($bucket, 'unknown', 0);
        $healthyCount = (int) data_get($bucket, 'up', 0);
        $checkedCount = (int) data_get($bucket, 'checked', 0);
        $baseColor = $downCount > 0 ? 0xE45757 : ($unknownCount > 0 ? 0xE0A43A : 0x6CC24A);

        $embeds = [[
            'title' => sprintf('%s Node Health Report', config('app.name', 'Panel')),
            'description' => 'Public node health summary for the latest detector run.',
            'color' => $baseColor,
            'fields' => [
                [
                    'name' => 'Total Nodes',
                    'value' => (string) $checkedCount,
                    'inline' => true,
                ],
                [
                    'name' => 'Healthy',
                    'value' => (string) $healthyCount,
                    'inline' => true,
                ],
                [
                    'name' => 'Need Attention',
                    'value' => (string) ($downCount + $unknownCount),
                    'inline' => true,
                ],
                [
                    'name' => 'Down',
                    'value' => (string) $downCount,
                    'inline' => true,
                ],
                [
                    'name' => 'Unknown',
                    'value' => (string) $unknownCount,
                    'inline' => true,
                ],
                [
                    'name' => 'Last Run',
                    'value' => $this->discordTimestampString($checkedAt->getTimestamp()),
                    'inline' => true,
                ],
                [
                    'name' => $outages === [] ? 'Status' : 'Attention Items',
                    'value' => $this->embedTextBlock($outages, 'No active node outages detected.'),
                    'inline' => false,
                ],
            ],
            'footer' => [
                'text' => sprintf(
                    'Public report • detector every %ds • summary every %d minutes',
                    (int) config('down_detector.interval_seconds', 60),
                    (int) config('down_detector.node.periodic_report_interval_minutes', 1440)
                ),
            ],
            'timestamp' => $checkedAt->toIso8601String(),
        ]];

        if ($nodeRows->isNotEmpty()) {
            $embeds = array_merge($embeds, $this->buildNodeStatusEmbeds($nodeRows, $checkedAt, $baseColor));
        }

        return [
            'content' => null,
            'embeds' => $embeds,
            'allowed_mentions' => ['parse' => []],
        ];
    }

    private function scopeLabel(string $scope): string
    {
        return match ($scope) {
            'node' => 'Node',
            'server' => 'Server',
            default => 'Down Detector',
        };
    }

    private function channelIdForScope(string $scope): ?string
    {
        $legacy = trim((string) config('down_detector.discord.channel_id', ''));
        $channel = match ($scope) {
            'node' => trim((string) config('down_detector.node.discord.alert_channel_id', '')),
            'server' => trim((string) config('down_detector.server.discord.alert_channel_id', '')),
            default => '',
        };

        $resolved = $channel !== '' ? $channel : $legacy;

        return $resolved !== '' ? $resolved : null;
    }

    private function serverLauncherChannelId(): ?string
    {
        $channel = trim((string) config('down_detector.server.discord.launcher_channel_id', ''));

        return $channel !== '' ? $channel : null;
    }

    private function postMessage(string $channelId, string $content): void
    {
        $this->postPayload($channelId, [
            'content' => $content,
            'allowed_mentions' => ['parse' => []],
        ]);
    }

    private function postPayload(string $channelId, array $payload): void
    {
        $response = $this->botHttp()->post(sprintf('%s/channels/%s/messages', self::API_BASE, $channelId), $payload);

        if (!$response->successful()) {
            throw new RuntimeException(sprintf(
                'Unable to send the down detector report to Discord: %s',
                $this->discordErrorMessage($response, 'Unknown Discord API error.')
            ));
        }
    }

    private function botHttp(): PendingRequest
    {
        $botToken = trim((string) config('services.discord.bot_token', ''));
        if ($botToken === '') {
            throw new RuntimeException('Set the panel Discord bot token before sending a down detector report.');
        }

        return Http::acceptJson()
            ->withToken($botToken, 'Bot')
            ->timeout((int) config('pterodactyl.guzzle.timeout', 30))
            ->connectTimeout((int) config('pterodactyl.guzzle.connect_timeout', 10));
    }

    private function embedBulletList(array $items, callable $formatter, int $maxItems = 6, int $limit = 1000): string
    {
        $lines = [];

        foreach (array_slice($items, 0, $maxItems) as $item) {
            $formatted = trim((string) $formatter($item));
            if ($formatted === '') {
                continue;
            }

            $lines[] = sprintf('- %s', $formatted);
        }

        if (count($items) > $maxItems) {
            $lines[] = sprintf('- ...and %d more', count($items) - $maxItems);
        }

        return $this->truncateEmbedValue(implode("\n", $lines), $limit);
    }

    private function embedTextBlock(array $lines, string $empty, int $maxItems = 4, int $limit = 1000): string
    {
        $content = $lines === []
            ? $empty
            : implode("\n\n", array_slice($lines, 0, $maxItems));

        if (count($lines) > $maxItems) {
            $content .= sprintf("\n\n...and %d more", count($lines) - $maxItems);
        }

        return $this->truncateEmbedValue($content, $limit);
    }

    private function truncateEmbedValue(string $value, int $limit): string
    {
        $trimmed = trim($value);

        return mb_strlen($trimmed) <= $limit
            ? $trimmed
            : rtrim(Str::limit($trimmed, $limit, '')) . '...';
    }

    private function nodeSnapshotRows(): Collection
    {
        $rows = DownDetectorMonitor::query()
            ->with('monitorable')
            ->where('monitorable_type', Node::class)
            ->get()
            ->map(function (DownDetectorMonitor $monitor): array {
                $node = $monitor->monitorable instanceof Node ? $monitor->monitorable : null;
                $meta = is_array($monitor->last_meta) ? $monitor->last_meta : [];

                return [
                    'name' => $node?->name ?? sprintf('Deleted node #%d', (int) $monitor->monitorable_id),
                    'status' => (string) $monitor->current_status,
                    'reason' => trim((string) ($monitor->last_reason ?? '')),
                    'message' => trim((string) ($monitor->last_message ?? '')),
                    'connection' => $node?->getConnectionAddress() ?? trim((string) data_get($meta, 'connection', '')),
                    'version' => trim((string) data_get($meta, 'payload.version', '')),
                    'checked_at' => $monitor->last_checked_at?->format('Y-m-d H:i:s T') ?? 'Never',
                    'changed_at' => $monitor->last_status_changed_at?->format('Y-m-d H:i:s T') ?? 'Never',
                    'checked_at_timestamp' => $monitor->last_checked_at?->getTimestamp(),
                    'changed_at_timestamp' => $monitor->last_status_changed_at?->getTimestamp(),
                    'consecutive_failures' => (int) $monitor->consecutive_failures,
                    'consecutive_successes' => (int) $monitor->consecutive_successes,
                ];
            });

        $priority = [
            DownDetectorMonitor::STATUS_DOWN => 0,
            DownDetectorMonitor::STATUS_UNKNOWN => 1,
            DownDetectorMonitor::STATUS_UP => 2,
            DownDetectorMonitor::STATUS_IGNORED => 3,
        ];

        return $rows->sort(function (array $left, array $right) use ($priority): int {
            $leftPriority = $priority[$left['status']] ?? 99;
            $rightPriority = $priority[$right['status']] ?? 99;

            if ($leftPriority !== $rightPriority) {
                return $leftPriority <=> $rightPriority;
            }

            return strcmp(Str::lower($left['name']), Str::lower($right['name']));
        })->values();
    }

    private function buildNodeStatusEmbeds(Collection $rows, CarbonInterface $checkedAt, int $baseColor): array
    {
        $chunks = $rows->chunk(6)->values();
        $total = $chunks->count();

        return $chunks->map(function (Collection $chunk, int $index) use ($checkedAt, $total, $baseColor): array {
            return [
                'title' => $total > 1
                    ? sprintf('Node Status Board %d/%d', $index + 1, $total)
                    : 'Node Status Board',
                'color' => $baseColor,
                'fields' => $chunk->map(fn (array $row) => $this->buildNodeStatusField($row))->all(),
                'footer' => [
                    'text' => 'Most important issues are listed first.',
                ],
                'timestamp' => $checkedAt->toIso8601String(),
            ];
        })->all();
    }

    private function buildNodeStatusField(array $row): array
    {
        $lines = [];

        if ($row['connection'] !== '') {
            $lines[] = sprintf('**Endpoint** `%s`', $row['connection']);
        }

        $lines[] = sprintf('**Status** %s', $this->statusLabel((string) $row['status']));

        if (($row['checked_at_timestamp'] ?? null) !== null) {
            $lines[] = sprintf('**Last Check** %s', $this->discordTimestampString((int) $row['checked_at_timestamp']));
        } else {
            $lines[] = sprintf('**Last Check** %s', $row['checked_at']);
        }

        if ($row['status'] === DownDetectorMonitor::STATUS_UP && $row['version'] !== '') {
            $lines[] = sprintf('**Wings** `%s`', $row['version']);
        }

        if ($row['status'] !== DownDetectorMonitor::STATUS_UP) {
            if ($row['reason'] !== '') {
                $lines[] = sprintf('**Issue** `%s`', $row['reason']);
            }

            if ($row['message'] !== '') {
                $lines[] = sprintf('**Detail** %s', Str::limit($row['message'], 160, '...'));
            }

            if (($row['changed_at_timestamp'] ?? null) !== null) {
                $lines[] = sprintf('**Changed** %s', $this->discordTimestampString((int) $row['changed_at_timestamp']));
            } else {
                $lines[] = sprintf('**Changed** %s', $row['changed_at']);
            }
        }

        return [
            'name' => sprintf('%s %s', $this->statusEmoji((string) $row['status']), $row['name']),
            'value' => $this->truncateEmbedValue(implode("\n", $lines), 900),
            'inline' => false,
        ];
    }

    private function statusEmoji(string $status): string
    {
        return match ($status) {
            DownDetectorMonitor::STATUS_UP => '🟢',
            DownDetectorMonitor::STATUS_DOWN => '🔴',
            DownDetectorMonitor::STATUS_IGNORED => '⚪',
            default => '🟠',
        };
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            DownDetectorMonitor::STATUS_UP => 'Healthy',
            DownDetectorMonitor::STATUS_DOWN => 'Down',
            DownDetectorMonitor::STATUS_IGNORED => 'Ignored',
            default => 'Unknown',
        };
    }

    private function discordTimestampString(int $timestamp): string
    {
        return sprintf('<t:%d:f> (<t:%d:R>)', $timestamp, $timestamp);
    }

    private function discordErrorMessage(Response $response, string $default): string
    {
        $body = trim((string) $response->body());

        return $body !== '' ? $body : ($default !== '' ? $default : ('HTTP ' . $response->status()));
    }
}
