<?php

namespace Pterodactyl\Services\Servers\Players\Providers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Models\Server;
use Pterodactyl\Services\Servers\Players\GameType;
use Pterodactyl\Services\Servers\Players\Support\PlayerScope;

class TerrariaLivePlayerProvider extends AbstractLivePlayerProvider
{
    public function gameType(): string
    {
        return GameType::TERRARIA;
    }

    public function gameLabel(): string
    {
        return GameType::label(GameType::TERRARIA);
    }

    protected function fetchPlayersFromServer(Server $server): array
    {
        try {
            $baseUrl = $this->getTShockRestUrl($server);
            if (!$baseUrl) return [];

            $token = $this->getTShockToken($server);
            if (!$token) {
                return $this->fetchPlayersViaRcon($server);
            }

            $response = Http::timeout(8)
                ->withHeaders(['X-Token' => $token])
                ->get($baseUrl . '/v2/players');

            if (!$response->successful()) {
                Log::warning('TShock REST API returned non-successful response.', [
                    'server_id' => $server->id,
                    'status' => $response->status(),
                ]);
                return $this->fetchPlayersViaRcon($server);
            }

            $data = $response->json();
            $players = $data['players'] ?? [];

            if (!is_array($players)) return [];

            $mapped = [];
            foreach ($players as $player) {
                $name = trim((string) ($player['name'] ?? ''));
                if ($name === '') continue;

                $uuid = (string) ($player['uuid'] ?? $player['account'] ?? '');
                $group = $player['group'] ?? 'player';

                $mapped[] = [
                    'id' => $uuid ?: md5($name),
                    'name' => $name,
                    'uuid' => $uuid,
                    'source_id' => $name,
                    'status' => 'online',
                    'ping' => (int) ($player['ping'] ?? 0),
                    'role' => in_array(strtolower($group), ['admin', 'owner', 'superadmin']) ? 'admin' : 'player',
                    'country' => null,
                    'avatar_url' => sprintf('https://api.dicebear.com/9.x/identicon/svg?seed=%s', urlencode($name)),
                    'last_seen_at' => now()->toIso8601String(),
                    'meta' => [
                        'group' => $group,
                        'team' => $player['team'] ?? 0,
                        'active' => $player['active'] ?? true,
                        'state' => $player['state'] ?? 'playing',
                    ],
                ];
            }

            return $mapped;
        } catch (\Throwable $e) {
            Log::warning('Failed to fetch Terraria players.', [
                'server_id' => $server->id,
                'error' => $e->getMessage(),
            ]);
            return $this->fetchPlayersViaRcon($server);
        }
    }

    protected function fetchPlayerCountFromServer(Server $server): int
    {
        return count($this->fetchPlayersFromServer($server));
    }

    protected function fetchMaxPlayersFromServer(Server $server): int
    {
        try {
            $baseUrl = $this->getTShockRestUrl($server);
            if (!$baseUrl) return 0;

            $token = $this->getTShockToken($server);

            $response = Http::timeout(5)
                ->withHeaders(['X-Token' => $token])
                ->get($baseUrl . '/v2/server/status');

            if (!$response->successful()) return 0;

            $data = $response->json();
            return (int) ($data['maxplayers'] ?? 0);
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
                'message' => sprintf('/say [%s] %s', $player['name'], $context['text'] ?? 'Hello'),
                'teleport' => sprintf('/tp %s 0 80 0', $player['name']),
                'kick' => sprintf('/kick %s %s', $player['name'], $context['reason'] ?? 'Kicked from console'),
                'ban' => sprintf('/ban %s %s', $player['name'], $context['reason'] ?? 'Banned from console'),
                'minecraft.heal' => sprintf('/heal %s', $player['name']),
                'minecraft.kill' => sprintf('/kill %s', $player['name']),
                default => null,
            };

            if (!$command) {
                return ['success' => false, 'message' => 'Unsupported action.'];
            }

            $this->sendRconCommand($server, $command);

            return [
                'success' => true,
                'message' => ucfirst(str_replace('minecraft.', '', $actionId)) . ' command sent.',
                'action' => $actionId,
                'player' => $player['name'],
            ];
        } catch (\Throwable $e) {
            Log::error('Terraria action failed.', [
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
                'Terraria provider uses TShock REST API for real-time player data.',
                'Requires TShock plugin with REST enabled and proper token configuration.',
                'Falls back to RCON if REST API is not available.',
            ],
        ];
    }

    public function inventory(Server $server, string $playerId): array
    {
        return [
            'available' => false,
            'reason' => 'Inventory inspection is not available for Terraria.',
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
                        ['label' => 'Group', 'value' => ucfirst($meta['group'] ?? 'Player')],
                        ['label' => 'Team', 'value' => $meta['team'] ?? 'None'],
                        ['label' => 'State', 'value' => ucfirst($meta['state'] ?? 'Playing')],
                        ['label' => 'Ping', 'value' => $player['ping'] . 'ms'],
                    ],
                ],
            ],
            'is_dummy' => false,
            'player_id' => $playerId,
        ];
    }

    private function getTShockRestUrl(Server $server): ?string
    {
        $allocation = $server->allocations()->where('is_default', true)->first();
        if (!$allocation) return null;

        return sprintf('http://%s:%s/v2', $allocation->ip, $allocation->port);
    }

    private function getTShockToken(Server $server): ?string
    {
        $env = trim($server->startup ?? '');

        if (preg_match('/--tshock-token\s+(\S+)/', $env, $matches)) {
            return $matches[1];
        }

        if (preg_match('/TSHOCK_TOKEN=(\S+)/', $env, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function fetchPlayersViaRcon(Server $server): array
    {
        try {
            $result = $this->sendRconCommand($server, 'playing');
            if (!$result || $result === '') return [];

            $lines = explode("\n", trim($result));
            $players = [];

            foreach ($lines as $line) {
                $line = trim($line);
                if (preg_match('/^\d+\.\s+(.+)/', $line, $matches)) {
                    $name = trim($matches[1]);
                    if ($name && $name !== 'Nobody is playing') {
                        $players[] = [
                            'id' => md5($name),
                            'name' => $name,
                            'uuid' => md5($name),
                            'source_id' => $name,
                            'status' => 'online',
                            'ping' => 0,
                            'role' => 'player',
                            'country' => null,
                            'avatar_url' => sprintf('https://api.dicebear.com/9.x/identicon/svg?seed=%s', urlencode($name)),
                            'last_seen_at' => now()->toIso8601String(),
                        ];
                    }
                }
            }

            return $players;
        } catch (\Throwable $e) {
            return [];
        }
    }
}
