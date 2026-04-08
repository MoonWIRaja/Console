<?php

namespace Pterodactyl\Services\Servers\Players\Providers;

use Illuminate\Support\Facades\Log;
use Pterodactyl\Models\Server;
use Pterodactyl\Services\Servers\Players\GameType;
use Pterodactyl\Services\Servers\Players\Support\PlayerScope;

class MinecraftBedrockLivePlayerProvider extends AbstractLivePlayerProvider
{
    /**
     * @var array<int, array<string, array{name: string, xuid: string}>>
     */
    private array $identityMapCache = [];

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
            $allocation = $this->defaultAllocation($server);
            if (!$allocation) {
                return [];
            }

            $ip = $allocation->ip;
            $port = $allocation->port;

            $players = $this->queryBedrockPlayers($ip, $port);
            $identityMap = $this->bedrockIdentityMap($server);

            $mapped = [];
            foreach ($players as $name) {
                $name = $this->normalizeBedrockPlayerName($name);
                if ($name === '') {
                    continue;
                }

                $identity = $identityMap[mb_strtolower($name)] ?? null;
                if (!is_array($identity)) {
                    continue;
                }

                $resolvedName = $this->normalizeBedrockPlayerName((string) ($identity['name'] ?? ''));
                $xuid = $this->normalizeBedrockXuid((string) ($identity['xuid'] ?? ''));
                if ($resolvedName === '' || $xuid === '') {
                    continue;
                }

                if (mb_strtolower($resolvedName) !== mb_strtolower($name)) {
                    continue;
                }

                $mapped[] = [
                    'id' => 'xuid:' . $xuid,
                    'name' => $resolvedName,
                    'uuid' => '',
                    'source_id' => 'xuid:' . $xuid,
                    'status' => 'online',
                    'ping' => 0,
                    'role' => 'player',
                    'country' => null,
                    'avatar_url' => sprintf('https://mc-heads.net/avatar/%s/64', rawurlencode($resolvedName)),
                    'last_seen_at' => now()->toIso8601String(),
                    'meta' => [
                        'xuid' => $xuid,
                    ],
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

    public function counts(Server $server): array
    {
        $players = $this->players($server);

        return [
            'total' => count($players),
            'online' => count(array_filter($players, fn (array $player) => ($player['status'] ?? 'offline') === 'online')),
            'operators' => 0,
            'admins' => 0,
            'staff' => 0,
            'banned' => 0,
            'max' => $this->fetchMaxPlayersFromServer($server),
        ];
    }

    protected function fetchPlayerCountFromServer(Server $server): int
    {
        return count($this->fetchPlayersFromServer($server));
    }

    protected function fetchMaxPlayersFromServer(Server $server): int
    {
        try {
            $allocation = $this->defaultAllocation($server);
            if (!$allocation) {
                return 0;
            }

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
        $onlineModeEnabled = $this->bedrockOnlineModeEnabled($server);

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
                $onlineModeEnabled
                    ? 'When direct XUID recovery is unavailable, the console page can fall back to live roster snapshots from the Bedrock list command.'
                    : 'Enable online-mode=true in server.properties to allow trusted Bedrock live roster fallback.',
                'Only trusted Bedrock identities recovered from server files, logs, or online-mode roster snapshots are displayed.',
            ],
            'integrations' => [
                'bedrock_online_mode' => $onlineModeEnabled,
                'bedrock_console_roster_fallback' => $onlineModeEnabled,
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

    /**
     * @return array<string, array{name: string, xuid: string}>
     */
    private function bedrockIdentityMap(Server $server): array
    {
        if (isset($this->identityMapCache[$server->id])) {
            return $this->identityMapCache[$server->id];
        }

        $map = [];

        foreach (['/allowlist.json', '/whitelist.json', '/permissions.json'] as $path) {
            $map = array_replace($map, $this->identityMapFromJsonFile($server, $path));
        }

        $map = array_replace($map, $this->identityMapFromLatestLog($server));
        $this->identityMapCache[$server->id] = $map;

        return $map;
    }

    /**
     * @return array<string, array{name: string, xuid: string}>
     */
    private function identityMapFromJsonFile(Server $server, string $path): array
    {
        try {
            $content = $this->fileRepository
                ->setServer($server)
                ->getContent($path, 1024 * 1024);
        } catch (\Throwable) {
            return [];
        }

        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            return [];
        }

        $map = [];

        foreach ($decoded as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $name = $this->normalizeBedrockPlayerName((string) (
                $entry['name']
                ?? $entry['player_name']
                ?? $entry['gamertag']
                ?? $entry['display_name']
                ?? ''
            ));
            $xuid = $this->normalizeBedrockXuid((string) ($entry['xuid'] ?? $entry['player_id'] ?? $entry['id'] ?? ''));

            if ($name === '' || $xuid === '') {
                continue;
            }

            $map[mb_strtolower($name)] = [
                'name' => $name,
                'xuid' => $xuid,
            ];
        }

        return $map;
    }

    /**
     * @return array<string, array{name: string, xuid: string}>
     */
    private function identityMapFromLatestLog(Server $server): array
    {
        $content = $this->readFirstAvailableTextFile(
            $server,
            ['/logs/latest.log', '/server.log', '/bedrock_server.log', '/latest.log', '/Dedicated_Server.txt'],
            4 * 1024 * 1024
        );
        if ($content === '') {
            return [];
        }

        $map = [];
        $lines = preg_split('/\r\n|\r|\n/', $content) ?: [];

        foreach ($lines as $line) {
            $normalizedLine = trim((string) preg_replace('/\x1B\[[0-9;]*[A-Za-z]/', '', $line));
            if ($normalizedLine === '') {
                continue;
            }

            $patterns = [
                '/Player connected:\s*(.+?)(?:,)?\s+xuid:\s*([0-9]+)/iu',
                '/Player disconnected:\s*(.+?)(?:,)?\s+xuid:\s*([0-9]+)/iu',
                '/Player Spawned:\s*(.+?)(?:,)?\s+xuid:\s*([0-9]+)/iu',
            ];

            foreach ($patterns as $pattern) {
                if (!(bool) preg_match($pattern, $normalizedLine, $matches)) {
                    continue;
                }

                $name = $this->normalizeBedrockPlayerName((string) ($matches[1] ?? ''));
                $xuid = $this->normalizeBedrockXuid((string) ($matches[2] ?? ''));
                if ($name === '' || $xuid === '') {
                    continue;
                }

                $map[mb_strtolower($name)] = [
                    'name' => $name,
                    'xuid' => $xuid,
                ];

                break;
            }
        }

        return $map;
    }

    private function readFirstAvailableTextFile(Server $server, array $paths, int $maxBytes): string
    {
        foreach ($paths as $path) {
            try {
                $content = $this->fileRepository
                    ->setServer($server)
                    ->getContent($path, $maxBytes);

                if (trim($content) !== '') {
                    return $content;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return '';
    }

    private function normalizeBedrockPlayerName(string $value): string
    {
        $trimmed = trim($value, " \t\n\r\0\x0B\"'");
        $trimmed = preg_replace('/\s+/', ' ', $trimmed) ?? $trimmed;

        return trim($trimmed);
    }

    private function normalizeBedrockXuid(string $value): string
    {
        $digits = preg_replace('/\D+/', '', trim($value)) ?? '';

        return preg_match('/^\d{6,20}$/', $digits) === 1 ? $digits : '';
    }

    private function defaultAllocation(Server $server): mixed
    {
        $server->loadMissing('allocation');
        if ($server->allocation) {
            return $server->allocation;
        }

        return $server->allocations()->first();
    }

    private function bedrockOnlineModeEnabled(Server $server): bool
    {
        try {
            $content = $this->fileRepository
                ->setServer($server)
                ->getContent('/server.properties', 128 * 1024);
        } catch (\Throwable) {
            return false;
        }

        if (!(bool) preg_match('/^\s*online-mode\s*=\s*(true|false)\s*$/im', $content, $matches)) {
            return false;
        }

        return mb_strtolower((string) ($matches[1] ?? 'false')) === 'true';
    }
}
