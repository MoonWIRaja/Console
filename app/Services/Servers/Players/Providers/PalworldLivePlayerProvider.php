<?php

namespace Pterodactyl\Services\Servers\Players\Providers;

use Illuminate\Support\Facades\Log;
use Pterodactyl\Models\Server;
use Pterodactyl\Services\Servers\Players\GameType;
use Pterodactyl\Services\Servers\Players\Support\PlayerScope;

class PalworldLivePlayerProvider extends AbstractLivePlayerProvider
{
    public function gameType(): string
    {
        return GameType::PALWORLD;
    }

    public function gameLabel(): string
    {
        return GameType::label(GameType::PALWORLD);
    }

    protected function resolveRconCredentials(Server $server): ?array
    {
        // Verified live against a real Palworld server on this installation:
        // its egg's startup script pipes stdin through the "rcon" CLI tool onto
        // this RCON_PORT/ADMIN_PASSWORD pair, and connecting straight to that
        // port with these names authenticated and returned real ShowPlayers/Info
        // output.
        return $this->resolveRconFromVariables(
            $server,
            portVariables: ['RCON_PORT'],
            passwordVariables: ['ADMIN_PASSWORD'],
            defaultPort: 25575,
        );
    }

    protected function fetchPlayersFromServer(Server $server): array
    {
        try {
            // Palworld RCON command: ShowPlayers
            // Output is CSV: "name,playeruid,steamid" header, then one row per
            // online player (playeruid is Palworld's own numeric player id;
            // steamid may be blank on non-Steam platforms).
            $output = $this->sendRconCommand($server, 'ShowPlayers');

            if (!$output) return [];

            $lines = array_values(array_filter(array_map('trim', explode("\n", trim($output)))));
            if (empty($lines)) return [];

            // First line is always the "name,playeruid,steamid" header - skip it.
            array_shift($lines);

            $players = [];
            foreach ($lines as $line) {
                $parts = str_getcsv($line);
                $name = trim((string) ($parts[0] ?? ''));
                if ($name === '') continue;

                $playerUid = trim((string) ($parts[1] ?? ''));
                $steamId = trim((string) ($parts[2] ?? ''));
                $id = $steamId !== '' ? $steamId : $playerUid;

                $players[] = [
                    'id' => $id,
                    'name' => $name,
                    'uuid' => 'palworld_' . $playerUid,
                    'source_id' => $steamId !== '' ? 'steam:' . $steamId : 'palworld:' . $playerUid,
                    'status' => 'online',
                    'ping' => 0,
                    'role' => 'player',
                    'country' => null,
                    'avatar_url' => sprintf('https://api.dicebear.com/9.x/identicon/svg?seed=%s', urlencode($name)),
                    'last_seen_at' => now()->toIso8601String(),
                    'meta' => ['player_uid' => $playerUid],
                ];
            }

            return $players;
        } catch (\Throwable $e) {
            Log::warning('Failed to fetch Palworld players via RCON.', [
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
        // Palworld's RCON doesn't expose the configured max player slots.
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

        $playerUid = (string) ($player['meta']['player_uid'] ?? '');

        try {
            $command = match ($actionId) {
                'message' => sprintf('Broadcast %s', $context['text'] ?? 'Hello'),
                'kick' => sprintf('KickPlayer %s', $playerUid),
                'ban' => sprintf('BanPlayer %s', $playerUid),
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
            Log::error('Palworld action failed.', ['error' => $e->getMessage()]);
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
                'Palworld provider uses RCON for real-time player data.',
                'Requires RCONEnabled=True and a matching AdminPassword in PalWorldSettings.ini.',
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
                        ['label' => 'Game', 'value' => 'Palworld'],
                    ],
                ],
            ],
            'is_dummy' => false,
            'player_id' => $playerId,
        ];
    }
}
