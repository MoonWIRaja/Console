<?php

namespace Pterodactyl\Services\Servers\Players\Providers;

use Illuminate\Support\Facades\Log;
use Pterodactyl\Models\Server;
use Pterodactyl\Services\Servers\Players\GameType;
use Pterodactyl\Services\Servers\Players\Support\PlayerScope;

class ArkLivePlayerProvider extends AbstractLivePlayerProvider
{
    public function gameType(): string
    {
        return GameType::ARK;
    }

    public function gameLabel(): string
    {
        return GameType::label(GameType::ARK);
    }

    protected function fetchPlayersFromServer(Server $server): array
    {
        try {
            // ARK RCON command: listplayers
            $output = $this->sendRconCommand($server, 'listplayers');

            if (!$output) return [];

            // Example output: "0. PlayerName (SteamID: 76561198...)"
            $lines = explode("\n", trim($output));
            $players = [];

            foreach ($lines as $line) {
                $line = trim($line);
                // Parse: "0. Name (SteamID: ID)"
                if (preg_match('/^\d+\.\s+(.+?)\s+\(SteamID:\s*(\d+)\)/i', $line, $matches)) {
                    $name = trim($matches[1]);
                    $steamId = $matches[2];

                    $players[] = [
                        'id' => $steamId,
                        'name' => $name,
                        'uuid' => 'steam_' . $steamId,
                        'source_id' => 'steam:' . $steamId,
                        'status' => 'online',
                        'ping' => 0, // ARK RCON doesn't return ping
                        'role' => 'player',
                        'country' => null,
                        'avatar_url' => sprintf('https://api.dicebear.com/9.x/identicon/svg?seed=%s', urlencode($name)),
                        'last_seen_at' => now()->toIso8601String(),
                    ];
                }
            }

            return $players;
        } catch (\Throwable $e) {
            Log::warning('Failed to fetch ARK players via RCON.', [
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
        try {
            $output = $this->sendRconCommand($server, 'serverinfo');
            if (!$output) return 0;

            if (preg_match('/MaxPlayers:\s*(\d+)/i', $output, $matches)) {
                return (int) $matches[1];
            }
            return 0;
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
        if (!$player) return ['success' => false, 'message' => 'Player not found.'];

        try {
            $command = match ($actionId) {
                'message' => sprintf('broadcast %s', $context['text'] ?? 'Hello'),
                'teleport' => sprintf('teleportplayersteamid %s 0 0 0', $playerId),
                'kick' => sprintf('kickplayersteamid %s %s', $playerId, $context['reason'] ?? 'Kicked'),
                'ban' => sprintf('banplayersteamid %s %s', $playerId, $context['reason'] ?? 'Banned'),
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
            Log::error('ARK action failed.', ['error' => $e->getMessage()]);
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
                'ARK provider uses RCON for real-time player data.',
                'Requires RCON to be enabled in GameUserSettings.ini.',
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
                        ['label' => 'Platform', 'value' => 'Steam'],
                    ],
                ],
            ],
            'is_dummy' => false,
            'player_id' => $playerId,
        ];
    }
}
