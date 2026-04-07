<?php

namespace Pterodactyl\Services\Servers\Players\Providers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Models\Server;
use Pterodactyl\Services\Servers\Players\GameType;
use Pterodactyl\Services\Servers\Players\Support\PlayerCommandHelper;
use Pterodactyl\Services\Servers\Players\Support\PlayerScope;

class FiveMLivePlayerProvider extends AbstractLivePlayerProvider
{
    public function gameType(): string
    {
        return GameType::FIVEM;
    }

    public function gameLabel(): string
    {
        return GameType::label(GameType::FIVEM);
    }

    protected function fetchPlayersFromServer(Server $server): array
    {
        try {
            $endpoint = $this->getPlayerListEndpoint($server);
            if (!$endpoint) {
                return [];
            }

            $response = Http::timeout(8)->get($endpoint);

            if (!$response->successful()) {
                Log::warning('FiveM player list API returned non-successful response.', [
                    'server_id' => $server->id,
                    'status' => $response->status(),
                ]);
                return [];
            }

            $players = $response->json();

            if (!is_array($players)) {
                return [];
            }

            $mapped = [];
            foreach ($players as $player) {
                $id = (string) ($player['id'] ?? $player['source'] ?? '');
                if ($id === '') continue;

                $name = trim((string) ($player['name'] ?? 'Unknown'));
                if ($name === '') continue;

                $ping = (int) ($player['ping'] ?? 0);
                $identifiers = $player['identifiers'] ?? [];

                $steamId = $this->extractIdentifier($identifiers, 'steam:');
                $license = $this->extractIdentifier($identifiers, 'license:');
                $discord = $this->extractIdentifier($identifiers, 'discord:');
                $xbl = $this->extractIdentifier($identifiers, 'xbl:');
                $live = $this->extractIdentifier($identifiers, 'live:');

                $mapped[] = [
                    'id' => $id,
                    'name' => $name,
                    'uuid' => $license ?: $steamId ?: $id,
                    'source_id' => $steamId ?: $id,
                    'status' => 'online',
                    'ping' => $ping,
                    'role' => 'player',
                    'country' => null,
                    'avatar_url' => $this->buildAvatarUrl($name),
                    'last_seen_at' => now()->toIso8601String(),
                    'meta' => [
                        'steam' => $steamId,
                        'license' => $license,
                        'discord' => $discord,
                        'xbl' => $xbl,
                        'live' => $live,
                    ],
                ];
            }

            return $mapped;
        } catch (\Throwable $e) {
            Log::warning('Failed to fetch FiveM players.', [
                'server_id' => $server->id,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    protected function fetchPlayerCountFromServer(Server $server): int
    {
        $players = $this->fetchPlayersFromServer($server);
        return count($players);
    }

    protected function fetchMaxPlayersFromServer(Server $server): int
    {
        try {
            $endpoint = $this->getInfoEndpoint($server);
            if (!$endpoint) {
                return 0;
            }

            $response = Http::timeout(5)->get($endpoint);

            if (!$response->successful()) {
                return 0;
            }

            $data = $response->json();
            return (int) ($data['vars']['sv_maxclients'] ?? $data['sv_maxclients'] ?? 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    protected function performActionOnServer(
        Server $server,
        string $playerId,
        string $actionId,
        array $context = []
    ): array {
        $player = $this->findPlayerById($server, $playerId);
        if (!$player) {
            return ['success' => false, 'message' => 'Player not found.'];
        }

        try {
            $command = match ($actionId) {
                'message' => sprintf('say [%s] %s', $context['text'] ?? 'Message', $player['name']),
                'teleport' => sprintf('tpm %s', $playerId),
                'kick' => sprintf('kick %s %s', $playerId, $context['reason'] ?? 'Kicked from console'),
                'ban' => sprintf('ban %s %s', $playerId, $context['reason'] ?? 'Banned from console'),
                default => null,
            };

            if (!$command) {
                return ['success' => false, 'message' => 'Unsupported action.'];
            }

            $this->sendRconCommand($server, $command);

            return [
                'success' => true,
                'message' => ucfirst($actionId) . ' command sent.',
                'action' => $actionId,
                'player' => $player['name'],
            ];
        } catch (\Throwable $e) {
            Log::error('FiveM action failed.', [
                'server_id' => $server->id,
                'action' => $actionId,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function capabilities(Server $server): array
    {
        return [
            'supports_live_data' => true,
            'supports_player_list' => true,
            'supports_counts' => true,
            'supported_scopes' => PlayerScope::all(),
            'filters' => [
                ['id' => 'name', 'label' => 'Search by Name', 'description' => 'Filter players by name.'],
            ],
            'notes' => [
                'FiveM provider fetches real-time data from the server\'s HTTP API.',
                'Requires the FiveM server to have the players endpoint accessible.',
            ],
        ];
    }

    public function inventory(Server $server, string $playerId): array
    {
        return [
            'available' => false,
            'reason' => 'Inventory inspection is not available for FiveM.',
            'is_dummy' => false,
            'player_id' => $playerId,
        ];
    }

    public function statistics(Server $server, string $playerId): array
    {
        $player = $this->findPlayerById($server, $playerId);
        if (!$player) {
            return ['available' => false, 'player_id' => $playerId];
        }

        $meta = $player['meta'] ?? [];

        return [
            'available' => true,
            'categories' => [
                [
                    'id' => 'session',
                    'title' => 'Session',
                    'entries' => [
                        ['label' => 'Ping', 'value' => $player['ping'] . 'ms'],
                        ['label' => 'Status', 'value' => ucfirst($player['status'])],
                    ],
                ],
                [
                    'id' => 'identifiers',
                    'title' => 'Identifiers',
                    'entries' => array_filter([
                        isset($meta['steam']) ? ['label' => 'Steam', 'value' => $meta['steam']] : null,
                        isset($meta['discord']) ? ['label' => 'Discord', 'value' => $meta['discord']] : null,
                        isset($meta['xbl']) ? ['label' => 'Xbox', 'value' => $meta['xbl']] : null,
                        isset($meta['live']) ? ['label' => 'Microsoft', 'value' => $meta['live']] : null,
                    ]),
                ],
            ],
            'is_dummy' => false,
            'player_id' => $playerId,
        ];
    }

    protected function getPlayerListEndpoint(Server $server): ?string
    {
        $allocation = $server->allocations()->where('is_default', true)->first();
        if (!$allocation) return null;

        return sprintf('http://%s:%s/players.json', $allocation->ip, $allocation->port);
    }

    protected function getInfoEndpoint(Server $server): ?string
    {
        $allocation = $server->allocations()->where('is_default', true)->first();
        if (!$allocation) return null;

        return sprintf('http://%s:%s/info.json', $allocation->ip, $allocation->port);
    }

    private function extractIdentifier(array $identifiers, string $prefix): ?string
    {
        foreach ($identifiers as $id) {
            if (str_starts_with($id, $prefix)) {
                return $id;
            }
        }
        return null;
    }

    private function buildAvatarUrl(string $name): string
    {
        return sprintf('https://api.dicebear.com/9.x/identicon/svg?seed=%s', urlencode($name));
    }
}
