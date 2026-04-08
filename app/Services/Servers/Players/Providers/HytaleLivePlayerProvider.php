<?php

namespace Pterodactyl\Services\Servers\Players\Providers;

use Illuminate\Support\Facades\Log;
use Pterodactyl\Models\Server;
use Pterodactyl\Services\Servers\Players\GameType;
use Pterodactyl\Services\Servers\Players\Support\PlayerScope;

class HytaleLivePlayerProvider extends AbstractLivePlayerProvider
{
    public function gameType(): string
    {
        return GameType::HYTALE;
    }

    public function gameLabel(): string
    {
        return GameType::label(GameType::HYTALE);
    }

    protected function fetchPlayersFromServer(Server $server): array
    {
        try {
            // Hytale RCON command: list
            $output = $this->sendRconCommand($server, 'list');

            if (!$output) return [];

            // Example: "There are 2/20 players: PlayerA, PlayerB"
            if (preg_match('/There are \d+\/\d+ players:(.*)/i', $output, $matches)) {
                $names = explode(',', $matches[1]);
                $players = [];

                foreach ($names as $name) {
                    $name = trim($name);
                    if ($name !== '') {
                        $players[] = [
                            'id' => md5($name),
                            'name' => $name,
                            'uuid' => 'hytale_' . md5($name),
                            'source_id' => $name,
                            'status' => 'online',
                            'ping' => 0,
                            'role' => 'player',
                            'country' => null,
                            'avatar_url' => sprintf('https://crafatar.com/avatars/%s?size=64&overlay', md5($name)),
                            'last_seen_at' => now()->toIso8601String(),
                        ];
                    }
                }
                return $players;
            }

            return [];
        } catch (\Throwable $e) {
            Log::warning('Failed to fetch Hytale players via RCON.', [
                'server_id' => $server->id,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    protected function fetchPlayerCountFromServer(Server $server): int
    {
        $output = $this->sendRconCommand($server, 'list');
        if ($output && preg_match('/There are (\d+)\/\d+ players/i', $output, $matches)) {
            return (int) $matches[1];
        }
        return 0;
    }

    protected function fetchMaxPlayersFromServer(Server $server): int
    {
        $output = $this->sendRconCommand($server, 'list');
        if ($output && preg_match('/There are \d+\/(\d+) players/i', $output, $matches)) {
            return (int) $matches[1];
        }
        return 0;
    }

    protected function performActionOnServer(
        Server $server,
        string $playerId,
        string $actionId,
        array $context = []
    ): array {
        $player = $this->findPlayerById($server, $playerId);
        if (!$player) return ['success' => false, 'message' => 'Player not found.'];

        try {
            $command = match ($actionId) {
                'message' => sprintf('tellraw %s {"text":"%s"}', $player['name'], $context['text'] ?? 'Hello'),
                'teleport' => sprintf('teleport %s 0 80 0', $player['name']),
                'kick' => sprintf('kick %s %s', $player['name'], $context['reason'] ?? 'Kicked from console'),
                'ban' => sprintf('ban %s %s', $player['name'], $context['reason'] ?? 'Banned from console'),
                'minecraft.heal' => sprintf('effect give %s minecraft:instant_health 1 1 true', $player['name']),
                'minecraft.kill' => sprintf('kill %s', $player['name']),
                default => null,
            };

            if (!$command) return ['success' => false, 'message' => 'Unsupported action.'];

            $this->sendRconCommand($server, $command);

            return [
                'success' => true,
                'message' => ucfirst(str_replace('minecraft.', '', $actionId)) . ' command sent.',
                'action' => $actionId,
                'player' => $player['name'],
            ];
        } catch (\Throwable $e) {
            Log::error('Hytale action failed.', ['error' => $e->getMessage()]);
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
                'Hytale provider uses RCON for real-time player data.',
                'Requires RCON to be enabled in server settings.',
            ],
        ];
    }

    public function inventory(Server $server, string $playerId): array
    {
        return ['available' => false, 'reason' => 'Inventory inspection not available via RCON.', 'is_dummy' => false, 'player_id' => $playerId];
    }

    public function statistics(Server $server, string $playerId): array
    {
        $player = $this->findPlayerById($server, $playerId);
        if (!$player) return ['available' => false, 'player_id' => $playerId];

        return [
            'available' => true,
            'categories' => [
                [
                    'id' => 'session',
                    'title' => 'Session',
                    'entries' => [
                        ['label' => 'Status', 'value' => ucfirst($player['status'])],
                        ['label' => 'Game', 'value' => 'Hytale'],
                    ],
                ],
            ],
            'is_dummy' => false,
            'player_id' => $playerId,
        ];
    }
}
