<?php

namespace Pterodactyl\Services\AlwaysMotd;

use Throwable;
use Illuminate\Support\Str;
use Pterodactyl\Models\Server;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;
use Pterodactyl\Services\Servers\Players\GameType;
use Pterodactyl\Services\Servers\Players\Support\PlayerGameTypeResolver;

class LiveMinecraftMotdSyncService
{
    public function __construct(
        private AlwaysMotdConfigService $configService,
        private DaemonFileRepository $fileRepository,
        private PlayerGameTypeResolver $gameTypeResolver,
    ) {
    }

    public function enabled(): bool
    {
        return (bool) data_get($this->configService->load(), 'live.enabled', true);
    }

    public function supports(Server $server): bool
    {
        $server->loadMissing(['egg', 'nest']);

        if (!$server->isInstalled()) {
            return false;
        }

        if (in_array($server->egg_id, $this->excludedEggIds(), true)) {
            return false;
        }

        $gameType = $this->gameTypeResolver->resolve($server);
        if (!in_array($gameType, [GameType::MINECRAFT_JAVA, GameType::MINECRAFT_BEDROCK], true)) {
            return false;
        }

        return $this->matchesConfiguredScope($server);
    }

    public function sync(Server $server): array
    {
        $server->loadMissing(['egg', 'nest', 'node']);

        if (!$this->enabled()) {
            return ['status' => 'skipped', 'reason' => 'disabled'];
        }

        if (!$this->supports($server)) {
            return ['status' => 'skipped', 'reason' => 'unsupported'];
        }

        $config = $this->configService->load();
        $gameType = $this->gameTypeResolver->resolve($server);
        $properties = $this->loadServerProperties($server);
        $updatedProperties = match ($gameType) {
            GameType::MINECRAFT_BEDROCK => $this->syncBedrockProperties($properties, $config),
            default => $this->syncJavaProperties($properties, $config),
        };

        $repository = $this->fileRepository->setServer($server);
        $repository->putContent('/server.properties', $updatedProperties);

        $iconSynced = false;
        $iconPath = $this->configService->getRuntimeIconPath();
        if ((bool) data_get($config, 'live.syncServerIcon', true) && is_file($iconPath)) {
            $iconContents = file_get_contents($iconPath);
            if ($iconContents !== false) {
                $repository->putRawContent('/server-icon.png', $iconContents, 'image/png');
                $iconSynced = true;
            }
        }

        return [
            'status' => 'synced',
            'game_type' => $gameType,
            'icon_synced' => $iconSynced,
        ];
    }

    public function matchingServers(?array $identifiers = null): Collection
    {
        $servers = Server::query()->with(['egg', 'nest', 'node'])->orderBy('id')->get();
        if (empty($identifiers)) {
            return $servers;
        }

        $needle = collect($identifiers)
            ->map(static fn ($value) => trim((string) $value))
            ->filter()
            ->values();

        return $servers->filter(function (Server $server) use ($needle): bool {
            return $needle->contains((string) $server->id)
                || $needle->contains($server->uuid)
                || $needle->contains($server->uuidShort);
        })->values();
    }

    public function syncMatchingServers(?array $identifiers = null): array
    {
        $summary = [
            'synced' => 0,
            'skipped' => 0,
            'failed' => 0,
            'rows' => [],
        ];

        /** @var Server $server */
        foreach ($this->matchingServers($identifiers) as $server) {
            try {
                $result = $this->sync($server);
                $status = (string) ($result['status'] ?? 'skipped');

                if ($status === 'synced') {
                    $summary['synced']++;
                } else {
                    $summary['skipped']++;
                }

                $summary['rows'][] = [
                    'server_id' => $server->id,
                    'server_name' => $server->name,
                    'node_name' => $server->node?->name ?? '-',
                    'game_type' => (string) ($result['game_type'] ?? '-'),
                    'status' => $status,
                    'reason' => (string) ($result['reason'] ?? ''),
                ];
            } catch (Throwable $exception) {
                report($exception);

                $summary['failed']++;
                $summary['rows'][] = [
                    'server_id' => $server->id,
                    'server_name' => $server->name,
                    'node_name' => $server->node?->name ?? '-',
                    'game_type' => '-',
                    'status' => 'failed',
                    'reason' => $exception->getMessage(),
                ];
            }
        }

        return $summary;
    }

    private function loadServerProperties(Server $server): string
    {
        try {
            return $this->fileRepository
                ->setServer($server)
                ->getContent('/server.properties', 1024 * 1024);
        } catch (Throwable $exception) {
            Log::info('Live Minecraft MOTD sync could not read existing server.properties, creating a fresh file.', [
                'server_id' => $server->id,
                'server_uuid' => $server->uuid,
                'error' => $exception->getMessage(),
            ]);

            return '';
        }
    }

    private function syncJavaProperties(string $contents, array $config): string
    {
        $motd = $this->normalizeJavaMotd((string) data_get($config, 'live.runningDescription', ''));

        return $this->upsertProperties($contents, ['motd' => $motd]);
    }

    private function syncBedrockProperties(string $contents, array $config): string
    {
        $motd = $this->toPlainSingleLine((string) data_get($config, 'live.runningDescription', ''));

        return $this->upsertProperties($contents, ['server-name' => $motd]);
    }

    private function upsertProperties(string $contents, array $properties): string
    {
        $contents = str_replace(["\r\n", "\r"], "\n", $contents);
        $lines = $contents === '' ? [] : explode("\n", $contents);
        $keys = array_keys($properties);
        $found = array_fill_keys($keys, false);

        foreach ($lines as $index => $line) {
            foreach ($properties as $key => $value) {
                if (preg_match('/^\s*' . preg_quote($key, '/') . '\s*[=:]/', $line) === 1) {
                    $lines[$index] = $key . '=' . $value;
                    $found[$key] = true;
                    break;
                }
            }
        }

        foreach ($properties as $key => $value) {
            if (!$found[$key]) {
                $lines[] = $key . '=' . $value;
            }
        }

        while (!empty($lines) && end($lines) === '') {
            array_pop($lines);
        }

        return implode("\n", $lines) . "\n";
    }

    private function normalizeJavaMotd(string $value): string
    {
        $value = str_replace(["\r\n", "\r"], "\n", rtrim($value));
        $value = str_replace('\\', '\\\\', $value);

        return str_replace("\n", '\n', $value);
    }

    private function toPlainSingleLine(string $value): string
    {
        $value = preg_replace('/§[0-9A-FK-ORX]/iu', '', str_replace(["\r\n", "\r"], "\n", $value)) ?? '';
        $value = preg_replace('/\s+/', ' ', $value) ?? '';

        return trim(Str::limit($value, 191, ''));
    }

    private function matchesConfiguredScope(Server $server): bool
    {
        $config = $this->configService->load();
        $nestNames = collect((array) data_get($config, 'detection.nestNames', []))
            ->map(static fn ($value) => Str::lower(trim((string) $value)))
            ->filter()
            ->values();
        $eggNames = collect((array) data_get($config, 'detection.eggNames', []))
            ->map(static fn ($value) => Str::lower(trim((string) $value)))
            ->filter()
            ->values();
        $nestIds = collect((array) data_get($config, 'detection.nestIds', []))
            ->map(static fn ($value) => (int) $value)
            ->filter()
            ->values();
        $eggIds = collect((array) data_get($config, 'detection.eggIds', []))
            ->map(static fn ($value) => (int) $value)
            ->filter()
            ->values();

        if ($nestNames->isEmpty() && $eggNames->isEmpty() && $nestIds->isEmpty() && $eggIds->isEmpty()) {
            return true;
        }

        $nestName = Str::lower((string) ($server->nest?->name ?? ''));
        $eggName = Str::lower((string) ($server->egg?->name ?? ''));

        return $nestIds->contains((int) $server->nest_id)
            || $eggIds->contains((int) $server->egg_id)
            || $nestNames->contains($nestName)
            || $eggNames->contains($eggName);
    }

    private function excludedEggIds(): array
    {
        return array_values(array_filter(array_map(
            static fn ($value) => is_numeric($value) ? (int) $value : null,
            data_get($this->configService->load(), 'excludeEggs', [])
        ), static fn ($value) => !is_null($value)));
    }
}
