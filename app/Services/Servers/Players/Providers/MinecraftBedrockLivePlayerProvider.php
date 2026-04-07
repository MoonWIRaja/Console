<?php

namespace Pterodactyl\Services\Servers\Players\Providers;

use Illuminate\Support\Facades\Log;
use Pterodactyl\Models\Server;
use Pterodactyl\Services\Servers\Players\GameType;
use Pterodactyl\Services\Servers\Players\Support\PlayerScope;

class MinecraftBedrockLivePlayerProvider extends AbstractLivePlayerProvider
{
    public function gameType(): string
    {
        return GameType::MINECRAFT_BEDROCK;
    }

    public function gameLabel(): string
    {
        return GameType::label(GameType::MINECRAFT_BEDROCK);
    }

    protected function fetchPlayersFromServer(Server $server): array
    {
        try {
            $allocation = $server->allocations()->where('is_default', true)->first();
            if (!$allocation) return [];

            $ip = $allocation->ip;
            $port = $allocation->port;

            $players = $this->queryBedrockPlayers($ip, $port);

            $mapped = [];
            foreach ($players as $name) {
                $name = trim($name);
                if ($name === '') continue;

                $uuid = $this->generateBedrockUuid($name);

                $mapped[] = [
                    'id' => $uuid,
                    'name' => $name,
                    'uuid' => $uuid,
                    'source_id' => 'xuid:' . crc32($name),
                    'status' => 'online',
                    'ping' => 0,
                    'role' => 'player',
                    'country' => null,
                    'avatar_url' => sprintf('https://mc-heads.net/avatar/%s/64', urlencode($name)),
                    'last_seen_at' => now()->toIso8601String(),
                ];
            }

            return $mapped;
        } catch (\Throwable $e) {
            Log::warning('Failed to fetch Bedrock players.', [
                'server_id' => $server->id,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    protected function fetchPlayerCountFromServer(Server $server): int
    {
        try {
            $allocation = $server->allocations()->where('is_default', true)->first();
            if (!$allocation) return 0;

            $status = $this->queryBedrockStatus($allocation->ip, $allocation->port);
            return (int) ($status['numplayers'] ?? 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    protected function fetchMaxPlayersFromServer(Server $server): int
    {
        try {
            $allocation = $server->allocations()->where('is_default', true)->first();
            if (!$allocation) return 0;

            $status = $this->queryBedrockStatus($allocation->ip, $allocation->port);
            return (int) ($status['maxplayers'] ?? 0);
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
                'message' => sprintf('tell %s %s', $player['name'], $context['text'] ?? 'Hello'),
                'teleport' => sprintf('tp %s 0 80 0', $player['name']),
                'kick' => sprintf('kick %s %s', $player['name'], $context['reason'] ?? 'Kicked from console'),
                'ban' => sprintf('ban %s %s', $player['name'], $context['reason'] ?? 'Banned from console'),
                'clear-inventory' => sprintf('clear %s', $player['name']),
                default => null,
            };

            if (!$command) {
                return ['success' => false, 'message' => 'Unsupported action.'];
            }

            $this->sendRconCommand($server, $command);

            return [
                'success' => true,
                'message' => ucfirst(str_replace('-', ' ', $actionId)) . ' command sent.',
                'action' => $actionId,
                'player' => $player['name'],
            ];
        } catch (\Throwable $e) {
            Log::error('Bedrock action failed.', [
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
                'Bedrock provider uses MCPE Status Query protocol for player data.',
                'Requires "query-plugins=true" in server.properties for full player names.',
            ],
        ];
    }

    public function inventory(Server $server, string $playerId): array
    {
        return [
            'available' => false,
            'reason' => 'Inventory inspection is not available for Minecraft Bedrock.',
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

        return [
            'available' => true,
            'categories' => [
                [
                    'id' => 'session',
                    'title' => 'Session',
                    'entries' => [
                        ['label' => 'Status', 'value' => ucfirst($player['status'])],
                        ['label' => 'Platform', 'value' => 'Minecraft Bedrock'],
                    ],
                ],
            ],
            'is_dummy' => false,
            'player_id' => $playerId,
        ];
    }

    private function queryBedrockPlayers(string $ip, int $port): array
    {
        $status = $this->queryBedrockStatus($ip, $port);

        $playerString = $status['players'] ?? '';
        if ($playerString === '') return [];

        $names = array_map('trim', explode(',', $playerString));
        return array_filter($names, fn($n) => $n !== '');
    }

    private function queryBedrockStatus(string $ip, int $port): array
    {
        $socket = @fsockopen('udp://' . $ip, $port, $errno, $errstr, 3);
        if (!$socket) return [];

        stream_set_timeout($socket, 3);

        $token = random_int(1, PHP_INT_MAX);
        $packet = "\xFE\xFD" . "\x09" . pack('N', $token) . "\x00\x00\x00\x00\x00";

        fwrite($socket, $packet);
        $response = fread($socket, 2048);

        fclose($socket);

        if (!$response || strlen($response) < 9) return [];

        $responseToken = unpack('N', substr($response, 5, 4))[1];
        if ($responseToken !== $token) return [];

        $challenge = substr($response, 9);
        $challenge = rtrim($challenge, "\x00");

        $fullQuery = "\xFE\xFD" . "\x00" . pack('N', $token) . pack('N', (int)$challenge) . "\x00\x00\x00\x00";

        $socket2 = @fsockopen('udp://' . $ip, $port, $errno, $errstr, 3);
        if (!$socket2) return [];

        stream_set_timeout($socket2, 3);
        fwrite($socket2, $fullQuery);
        $fullResponse = fread($socket2, 8192);
        fclose($socket2);

        if (!$fullResponse || strlen($fullResponse) < 13) return [];

        $data = substr($fullResponse, 11);
        $data = rtrim($data, "\x00");

        $parts = explode("\x00\x01", $data, 2);
        if (count($parts) < 2) return [];

        $info = $parts[0];
        $players = $parts[1] ?? '';

        $infoParts = explode("\x00", $info);

        $result = [];
        $keys = ['hostname', 'gametype', 'map', 'numplayers', 'maxplayers', 'hostport', 'hostip'];
        $i = 0;

        foreach ($keys as $key) {
            $result[$key] = $infoParts[$i] ?? '';
            $i++;
        }

        if ($players !== '') {
            $result['players'] = $players;
        }

        return $result;
    }

    private function generateBedrockUuid(string $name): string
    {
        $hash = md5('bedrock:' . $name);
        return substr($hash, 0, 8) . '-' . substr($hash, 8, 4) . '-4' . substr($hash, 13, 3)
            . '-' . substr($hash, 16, 4) . '-' . substr($hash, 20, 12);
    }
}
