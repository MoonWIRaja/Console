<?php

namespace Pterodactyl\Services\Servers\Players\Providers;

use Illuminate\Support\Facades\Log;
use Pterodactyl\Models\Server;
use Pterodactyl\Services\Servers\Players\GameType;
use Pterodactyl\Services\Servers\Players\Support\PlayerScope;

class ProjectZomboidLivePlayerProvider extends AbstractLivePlayerProvider
{
    public function gameType(): string
    {
        return GameType::PROJECT_ZOMBOID;
    }

    public function gameLabel(): string
    {
        return GameType::label(GameType::PROJECT_ZOMBOID);
    }

    protected function fetchPlayersFromServer(Server $server): array
    {
        try {
            // PZ RCON command: playerlist
            $output = $this->sendRconCommand($server, 'playerlist');

            if (!$output) return [];

            // Example output: "Player: Username (ID: 0, OnlineID: 76561198...)"
            $lines = explode("\n", trim($output));
            $players = [];

            foreach ($lines as $line) {
                $line = trim($line);
                // Parse: "Player: Name (ID: X, OnlineID: Y)"
                if (preg_match('/Player:\s+(.+?)\s+\(ID:\s*\d+,\s*OnlineID:\s*(\d+)\)/i', $line, $matches)) {
                    $name = trim($matches[1]);
                    $onlineId = $matches[2];

                    $players[] = [
                        'id' => $onlineId,
                        'name' => $name,
                        'uuid' => 'pz_' . $onlineId,
                        'source_id' => 'steam:' . $onlineId,
                        'status' => 'online',
                        'ping' => 0,
                        'role' => 'player',
                        'country' => null,
                        'avatar_url' => sprintf('https://api.dicebear.com/9.x/identicon/svg?seed=%s', urlencode($name)),
                        'last_seen_at' => now()->toIso8601String(),
                    ];
                }
            }

            return $players;
        } catch (\Throwable $e) {
            Log::warning('Failed to fetch PZ players via RCON.', [
                'server_id' => $server->id,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    protected function fetchPlayerCountFromServer(Server $server): int
    {
        return count($this->fetchPlayersFromServer($server));
    }

    protected function fetchMaxPlayersFromServer(Server $server): int
    {
        // PZ doesn't have a simple RCON command for max players, default to config or 0
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
                'message' => sprintf('say %s', $context['text'] ?? 'Hello'),
                'kick' => sprintf('kick %s %s', $playerId, $context['reason'] ?? 'Kicked from console'),
                'ban' => sprintf('ban %s %s', $playerId, $context['reason'] ?? 'Banned from console'),
                default => null,
            };

            if (!$command) return ['success' => false, 'message' => 'Unsupported action.'];

            $this->sendRconCommand($server, $command);

            return [
                'success' => true,
                'message' => ucfirst($actionId) . ' executed.',
                'action' => $actionId,
                'player' => $player['name'],
            ];
        } catch (\Throwable $e) {
            Log::error('PZ action failed.', ['error' => $e->getMessage()]);
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
                'Project Zomboid provider uses RCON for real-time player data.',
                'Requires RCON to be enabled in server config.',
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
                        ['label' => 'Game', 'value' => 'Project Zomboid'],
                    ],
                ],
            ],
            'is_dummy' => false,
            'player_id' => $playerId,
        ];
    }
}
