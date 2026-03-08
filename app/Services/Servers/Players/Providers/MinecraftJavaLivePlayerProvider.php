<?php

namespace Pterodactyl\Services\Servers\Players\Providers;

use Throwable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Pterodactyl\Models\Server;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;
use Pterodactyl\Repositories\Wings\DaemonCommandRepository;
use Pterodactyl\Services\Servers\Players\GameType;
use Pterodactyl\Services\Servers\Players\PlayerScope;
use Pterodactyl\Services\Servers\Players\Contracts\PlayerProviderInterface;
use Pterodactyl\Services\Servers\Players\Support\MinecraftNbtReader;

class MinecraftJavaLivePlayerProvider implements PlayerProviderInterface
{
    /**
     * @var array<int, array<int, array<string, mixed>>>
     */
    private array $playersCache = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    private array $statusCache = [];

    /**
     * @var array<int, array<string, string>>
     */
    private array $knownPlayerNamesCache = [];

    /**
     * @var array<int, array<int, string>>
     */
    private array $lastOnlineNamesCache = [];

    /**
     * @var array<string, string>
     */
    private array $officialUuidCacheByName = [];

    /**
     * @var array<string, array<string, mixed>|null>
     */
    private array $playerDataCache = [];

    /**
     * @var array<int, array<string, string>>
     */
    private array $serverPropertiesCache = [];

    /**
     * @var array<int, array<int, array{name:string,path:string,size:int}>>
     */
    private array $modsJarCache = [];

    private bool $modTextureLookupHadTransientError = false;

    public function __construct(
        private DaemonFileRepository $fileRepository,
        private DaemonCommandRepository $commandRepository,
    ) {
    }

    public function gameType(): string
    {
        return GameType::MINECRAFT_JAVA;
    }

    public function gameLabel(): string
    {
        return GameType::label(GameType::MINECRAFT_JAVA);
    }

    public function capabilities(Server $server): array
    {
        $status = $this->status($server);

        $notes = [];
        if (($status['query_enabled'] ?? false) === false) {
            $notes[] = 'Enable query in server.properties (enable-query=true) to expose exact online player names.';
        }
        if (($status['sample_available'] ?? false) === false) {
            $notes[] = 'Server status sample is unavailable; when hidden by server config, placeholder labels are used for online count.';
        }

        return [
            'filters' => [
                [
                    'id' => PlayerScope::ONLINE,
                    'label' => 'Online Players',
                    'description' => 'Only players currently connected.',
                ],
                [
                    'id' => PlayerScope::OPERATORS,
                    'label' => 'Operators',
                    'description' => 'Players with operator privileges.',
                ],
                [
                    'id' => PlayerScope::BANNED,
                    'label' => 'Banned',
                    'description' => 'Players currently banned.',
                ],
            ],
            'action_groups' => $this->actionGroups(),
            'tabs' => ['overview', 'inventory', 'statistics'],
            'notes' => $notes,
            'integrations' => [
                [
                    'id' => 'minecraft_query',
                    'label' => 'Minecraft Query/Status',
                    'state' => ($status['connected'] ?? false) ? 'connected' : 'degraded',
                ],
            ],
        ];
    }

    public function counts(Server $server): array
    {
        $players = $this->players($server);

        return [
            'total' => count($players),
            'online' => count(array_filter($players, fn (array $player) => ($player['status'] ?? 'offline') === 'online')),
            'operators' => count(array_filter($players, fn (array $player) => (bool) ($player['is_operator'] ?? false))),
            'admins' => count(array_filter($players, fn (array $player) => (bool) ($player['is_admin'] ?? false))),
            'staff' => count(array_filter($players, fn (array $player) => (bool) ($player['is_operator'] ?? false))),
            'banned' => count(array_filter($players, fn (array $player) => (bool) ($player['banned'] ?? false))),
        ];
    }

    public function list(Server $server, string $scope, ?string $search = null): array
    {
        $filtered = array_filter($this->players($server), function (array $player) use ($scope): bool {
            return match ($scope) {
                PlayerScope::ONLINE => ($player['status'] ?? 'offline') === 'online',
                PlayerScope::OPERATORS => (bool) ($player['is_operator'] ?? false),
                PlayerScope::BANNED => (bool) ($player['banned'] ?? false),
                default => true,
            };
        });

        $needle = mb_strtolower(trim((string) $search));
        if ($needle !== '') {
            $filtered = array_filter($filtered, function (array $player) use ($needle): bool {
                $haystack = mb_strtolower(implode(' ', [
                    (string) ($player['name'] ?? ''),
                    (string) ($player['id'] ?? ''),
                    (string) ($player['uuid'] ?? ''),
                    (string) ($player['source_id'] ?? ''),
                ]));

                return str_contains($haystack, $needle);
            });
        }

        usort($filtered, function (array $a, array $b): int {
            $aOnline = ($a['status'] ?? 'offline') === 'online' ? 0 : 1;
            $bOnline = ($b['status'] ?? 'offline') === 'online' ? 0 : 1;

            if ($aOnline !== $bOnline) {
                return $aOnline <=> $bOnline;
            }

            return strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
        });

        return array_values($filtered);
    }

    public function profile(Server $server, string $playerId): ?array
    {
        $player = $this->resolveProfilePlayer($server, $playerId);
        if (!$player) {
            return null;
        }

        return [
            ...$player,
            'is_dummy' => false,
            'action_groups' => $this->actionGroupsForProfile($player),
        ];
    }

    public function inventory(Server $server, string $playerId): array
    {
        $profile = $this->profile($server, $playerId);
        if (!$profile) {
            return [
                'available' => false,
                'message' => 'Player profile is unavailable.',
                'sections' => [],
                'summary' => [],
                'player_id' => $playerId,
                'is_dummy' => false,
            ];
        }

        $playerData = $this->loadPlayerData($server, $profile);
        if (!$playerData) {
            return [
                'available' => false,
                'message' => 'Unable to read playerdata file for this player yet. Ask the player to join/leave once, then retry.',
                'sections' => [],
                'summary' => [
                    ['label' => 'Player', 'value' => (string) ($profile['name'] ?? $playerId)],
                    ['label' => 'Status', 'value' => strtoupper((string) ($profile['status'] ?? 'offline'))],
                    ['label' => 'Ping', 'value' => (string) ((int) ($profile['ping'] ?? 0)) . 'ms'],
                ],
                'player_id' => $playerId,
                'is_dummy' => false,
            ];
        }

        $slots = $this->extractInventorySlots($playerData['nbt']);
        $totalItems = array_sum(array_map(fn (array $slot): int => (int) ($slot['count'] ?? 0), $slots));
        $totalStacks = count($slots);

        return [
            'available' => true,
            'message' => 'Live data loaded from playerdata file.',
            'sections' => $this->buildInventorySections($server, $slots),
            'summary' => [
                ['label' => 'Player', 'value' => (string) ($profile['name'] ?? $playerId)],
                ['label' => 'Data World', 'value' => (string) ($playerData['world'] ?? 'world')],
                ['label' => 'Total Stacks', 'value' => (string) $totalStacks],
                ['label' => 'Total Items', 'value' => (string) $totalItems],
            ],
            'player_id' => $playerId,
            'is_dummy' => false,
        ];
    }

    public function statistics(Server $server, string $playerId): array
    {
        $profile = $this->profile($server, $playerId);
        if (!$profile) {
            return [
                'available' => false,
                'message' => 'Player profile is unavailable.',
                'categories' => [],
                'player_id' => $playerId,
                'is_dummy' => false,
            ];
        }

        $playerData = $this->loadPlayerData($server, $profile);
        $nbt = is_array($playerData['nbt'] ?? null) ? $playerData['nbt'] : [];

        $xpLevel = $this->numericTag($nbt['XpLevel'] ?? null);
        $xpTotal = $this->numericTag($nbt['XpTotal'] ?? null);
        $health = $this->numericTag($nbt['Health'] ?? null);
        $food = $this->numericTag($nbt['foodLevel'] ?? null);
        $selectedSlot = $this->numericTag($nbt['SelectedItemSlot'] ?? null);
        $gamemode = $this->effectiveGamemode($server, $profile, $nbt);

        $position = '-';
        $pos = $nbt['Pos'] ?? null;
        if (is_array($pos) && count($pos) >= 3) {
            $x = $this->numericTag($pos[0] ?? null);
            $y = $this->numericTag($pos[1] ?? null);
            $z = $this->numericTag($pos[2] ?? null);

            if ($x !== null && $y !== null && $z !== null) {
                $position = sprintf('%.1f, %.1f, %.1f', $x, $y, $z);
            }
        }

        return [
            'available' => true,
            'message' => $playerData ? 'Live data loaded from playerdata file.' : 'Limited live metadata only; playerdata file not found.',
            'categories' => [
                [
                    'id' => 'presence',
                    'title' => 'Presence',
                    'entries' => [
                        ['label' => 'Status', 'value' => strtoupper((string) ($profile['status'] ?? 'offline'))],
                        ['label' => 'Ping', 'value' => (string) ((int) ($profile['ping'] ?? 0)) . 'ms'],
                        ['label' => 'Operator', 'value' => (bool) ($profile['is_operator'] ?? false) ? 'Yes' : 'No'],
                        ['label' => 'Banned', 'value' => (bool) ($profile['banned'] ?? false) ? 'Yes' : 'No'],
                    ],
                ],
                [
                    'id' => 'identity',
                    'title' => 'Identity',
                    'entries' => [
                        ['label' => 'Name', 'value' => (string) ($profile['name'] ?? '')],
                        ['label' => 'UUID', 'value' => (string) ($profile['uuid'] ?? '')],
                        ['label' => 'Data UUID', 'value' => (string) ($playerData['uuid'] ?? '-')],
                    ],
                ],
                [
                    'id' => 'minecraft_runtime',
                    'title' => 'Minecraft Runtime',
                    'entries' => [
                        ['label' => 'XP Level', 'value' => $xpLevel !== null ? (string) ((int) $xpLevel) : '-'],
                        ['label' => 'XP Total', 'value' => $xpTotal !== null ? (string) ((int) $xpTotal) : '-'],
                        ['label' => 'Health', 'value' => $health !== null ? (string) $health : '-'],
                        ['label' => 'Food Level', 'value' => $food !== null ? (string) ((int) $food) : '-'],
                        ['label' => 'Gamemode', 'value' => $gamemode],
                        ['label' => 'Selected Slot', 'value' => $selectedSlot !== null ? (string) ((int) $selectedSlot) : '-'],
                        ['label' => 'Position', 'value' => $position],
                    ],
                ],
            ],
            'player_id' => $playerId,
            'is_dummy' => false,
        ];
    }

    public function performAction(Server $server, string $playerId, string $action, array $context = []): array
    {
        $profile = $this->profile($server, $playerId);
        if (!$profile) {
            return [
                'accepted' => false,
                'queued' => false,
                'message' => 'Player not found.',
                'action' => $action,
                'player_id' => $playerId,
                'is_dummy' => false,
            ];
        }

        $map = [];
        foreach ($this->actionGroupsForProfile($profile) as $group) {
            foreach ((array) ($group['actions'] ?? []) as $item) {
                $map[(string) ($item['id'] ?? '')] = $item;
            }
        }

        $selected = $map[$action] ?? null;
        if (!$selected) {
            return [
                'accepted' => false,
                'queued' => false,
                'message' => 'Action is not supported.',
                'action' => $action,
                'player_id' => $playerId,
                'is_dummy' => false,
            ];
        }

        $replacements = [
            '{{player}}' => (string) ($profile['name'] ?? ''),
            '{{uuid}}' => (string) ($profile['uuid'] ?? ''),
            '{{id}}' => (string) ($profile['id'] ?? ''),
            '{{text}}' => (string) ($context['text'] ?? ''),
            '{{reason}}' => (string) ($context['reason'] ?? ''),
        ];

        foreach ($context as $key => $value) {
            if (!is_string($key) || $key === '') {
                continue;
            }

            $normalized = trim((string) (is_scalar($value) ? $value : ''));
            $replacements['{{' . $key . '}}'] = $normalized;
        }

        $command = str_replace(
            array_keys($replacements),
            array_values($replacements),
            (string) ($selected['command'] ?? '')
        );
        $command = trim(preg_replace('/\s+/', ' ', $command) ?? $command);

        if ($command === '') {
            return [
                'accepted' => false,
                'queued' => false,
                'message' => 'Generated command is empty.',
                'action' => $action,
                'player_id' => $playerId,
                'is_dummy' => false,
            ];
        }

        try {
            $this->commandRepository->setServer($server)->send($command);

            if ($action === 'minecraft.gamemode') {
                $modeInput = (string) ($context['mode'] ?? '');
                $resolvedMode = $this->gamemodeLabelFromInput($modeInput);
                if ($resolvedMode !== '-') {
                    Cache::put($this->gamemodeCacheKey($server, $profile), $resolvedMode, now()->addHours(6));
                }
            }

            return [
                'accepted' => true,
                'queued' => true,
                'message' => 'Command dispatched to the server console.',
                'action' => $action,
                'action_label' => (string) ($selected['label'] ?? $action),
                'player_id' => $playerId,
                'command_preview' => $command,
                'context' => $context,
                'is_dummy' => false,
            ];
        } catch (Throwable $exception) {
            return [
                'accepted' => false,
                'queued' => false,
                'message' => 'Unable to dispatch command to server console.',
                'action' => $action,
                'player_id' => $playerId,
                'command_preview' => $command,
                'is_dummy' => false,
            ];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function players(Server $server): array
    {
        if (isset($this->playersCache[$server->id])) {
            return $this->playersCache[$server->id];
        }

        $knownNames = $this->knownPlayerNames($server);
        $lastOnlineNames = $this->lastOnlineNames($server);
        $status = $this->status($server);
        $online = (array) ($status['players'] ?? []);
        $operators = $this->readOps($server);
        $banned = $this->readBanned($server);
        $resolvedOnlineNames = [];

        /** @var array<string, array<string, mixed>> $byKey */
        $byKey = [];

        foreach ($online as $index => $item) {
            $rawName = trim((string) ($item['name'] ?? ''));
            $uuid = $this->normalizeUuid((string) ($item['uuid'] ?? ''));
            $fallbackName = trim((string) ($lastOnlineNames[(int) $index] ?? ''));
            $name = $this->resolveDisplayName($rawName, $uuid, $knownNames, $fallbackName);
            if ($name === '') {
                continue;
            }

            $key = $uuid !== '' ? 'uuid:' . $uuid : 'name:' . mb_strtolower($name);
            $isOperator = $this->isInByNameOrUuid($operators, $name, $uuid);
            $isBanned = $this->isInByNameOrUuid($banned, $name, $uuid);

            $byKey[$key] = [
                'id' => $uuid !== '' ? $uuid : $this->idFromName($name),
                'name' => $name,
                'uuid' => $uuid,
                'source_id' => $uuid !== '' ? $uuid : $name,
                'status' => 'online',
                'ping' => max(0, (int) ($item['ping'] ?? 0)),
                'role' => $isOperator ? 'operator' : 'player',
                'is_operator' => $isOperator,
                'is_admin' => false,
                'banned' => $isBanned,
                'country' => '',
                'avatar_url' => $this->avatarFor($name, $uuid),
                'last_seen_at' => '',
                'is_dummy' => false,
            ];

            $this->rememberPlayerName($knownNames, $uuid, $name);
            if (!$this->isGenericPlayerName($name)) {
                $resolvedOnlineNames[(int) $index] = $name;
            }
        }

        foreach ($operators as $item) {
            $rawName = trim((string) ($item['name'] ?? ''));
            $uuid = $this->normalizeUuid((string) ($item['uuid'] ?? ''));
            $name = $this->resolveDisplayName($rawName, $uuid, $knownNames);
            if ($name === '') {
                continue;
            }

            $key = $uuid !== '' ? 'uuid:' . $uuid : 'name:' . mb_strtolower($name);

            if (!isset($byKey[$key])) {
                $byKey[$key] = [
                    'id' => $uuid !== '' ? $uuid : $this->idFromName($name),
                    'name' => $name,
                    'uuid' => $uuid,
                    'source_id' => $uuid !== '' ? $uuid : $name,
                    'status' => 'offline',
                    'ping' => 0,
                    'role' => 'operator',
                    'is_operator' => true,
                    'is_admin' => false,
                    'banned' => false,
                    'country' => '',
                    'avatar_url' => $this->avatarFor($name, $uuid),
                    'last_seen_at' => '',
                    'is_dummy' => false,
                ];

                continue;
            }

            if ($this->shouldReplaceDisplayName((string) ($byKey[$key]['name'] ?? ''), $name)) {
                $byKey[$key]['name'] = $name;
                $byKey[$key]['avatar_url'] = $this->avatarFor($name, $uuid);
            }
            $byKey[$key]['is_operator'] = true;
            $byKey[$key]['role'] = 'operator';
            $this->rememberPlayerName($knownNames, $uuid, $name);
        }

        foreach ($banned as $item) {
            $rawName = trim((string) ($item['name'] ?? ''));
            $uuid = $this->normalizeUuid((string) ($item['uuid'] ?? ''));
            $name = $this->resolveDisplayName($rawName, $uuid, $knownNames);
            if ($name === '') {
                continue;
            }

            $key = $uuid !== '' ? 'uuid:' . $uuid : 'name:' . mb_strtolower($name);

            if (!isset($byKey[$key])) {
                $byKey[$key] = [
                    'id' => $uuid !== '' ? $uuid : $this->idFromName($name),
                    'name' => $name,
                    'uuid' => $uuid,
                    'source_id' => $uuid !== '' ? $uuid : $name,
                    'status' => 'offline',
                    'ping' => 0,
                    'role' => 'player',
                    'is_operator' => false,
                    'is_admin' => false,
                    'banned' => true,
                    'country' => '',
                    'avatar_url' => $this->avatarFor($name, $uuid),
                    'last_seen_at' => '',
                    'is_dummy' => false,
                ];

                continue;
            }

            if ($this->shouldReplaceDisplayName((string) ($byKey[$key]['name'] ?? ''), $name)) {
                $byKey[$key]['name'] = $name;
                $byKey[$key]['avatar_url'] = $this->avatarFor($name, $uuid);
            }
            $byKey[$key]['banned'] = true;
            $this->rememberPlayerName($knownNames, $uuid, $name);
        }

        if (!empty($resolvedOnlineNames)) {
            $this->persistLastOnlineNames($server, $resolvedOnlineNames);
        }
        $this->persistKnownPlayerNames($server, $knownNames);
        $this->playersCache[$server->id] = array_values($byKey);

        return $this->playersCache[$server->id];
    }

    /**
     * @return array<string, mixed>
     */
    private function status(Server $server): array
    {
        if (isset($this->statusCache[$server->id])) {
            return $this->statusCache[$server->id];
        }

        $server->loadMissing(['allocation', 'node']);

        $hostCandidates = [];
        $ip = trim((string) ($server->allocation?->ip ?? ''));
        $alias = trim((string) ($server->allocation?->ip_alias ?? ''));
        $nodeFqdn = trim((string) ($server->node?->fqdn ?? ''));
        $port = (int) ($server->allocation?->port ?? 0);

        if ($ip !== '' && !in_array($ip, ['0.0.0.0', '::'], true)) {
            $hostCandidates[] = $ip;
        }
        if ($alias !== '') {
            $hostCandidates[] = $alias;
        }
        if ($nodeFqdn !== '') {
            $hostCandidates[] = $nodeFqdn;
        }

        $hostCandidates = array_values(array_unique($hostCandidates));
        $players = [];
        $connected = false;
        $queryEnabled = false;
        $sampleAvailable = false;
        $onlineCount = 0;

        foreach ($hostCandidates as $host) {
            if ($port <= 0) {
                continue;
            }

            $query = $this->queryPlayers($host, $port);
            if (!empty($query['players'])) {
                $players = $query['players'];
                $onlineCount = count($players);
                $connected = true;
                $queryEnabled = true;
                $sampleAvailable = true;
                break;
            }

            $status = $this->statusPlayers($host, $port);
            if (!$status['connected']) {
                continue;
            }

            $connected = true;
            $onlineCount = (int) ($status['online'] ?? 0);
            $samplePlayers = $status['players'];
            $sampleAvailable = count($samplePlayers) > 0;

            if ($sampleAvailable) {
                $players = $samplePlayers;
                break;
            }

            if ($onlineCount > 0) {
                $players = [];
                for ($i = 1; $i <= $onlineCount; $i++) {
                    $players[] = [
                        'name' => 'Online Player #' . $i,
                        'uuid' => '',
                        'ping' => (int) ($status['latency_ms'] ?? 0),
                    ];
                }
                break;
            }
        }

        $this->statusCache[$server->id] = [
            'connected' => $connected,
            'query_enabled' => $queryEnabled,
            'sample_available' => $sampleAvailable,
            'online_count' => $onlineCount,
            'players' => $players,
        ];

        return $this->statusCache[$server->id];
    }

    /**
     * @return array<string, mixed>
     */
    private function queryPlayers(string $host, int $port): array
    {
        try {
            $socket = @stream_socket_client(
                sprintf('udp://%s:%d', $host, $port),
                $errno,
                $error,
                0.9,
                STREAM_CLIENT_CONNECT
            );

            if (!is_resource($socket)) {
                return ['players' => []];
            }

            stream_set_timeout($socket, 1, 500000);

            $sessionId = random_int(1, 0x7FFFFFFF);
            $handshakePacket = "\xFE\xFD\x09" . pack('N', $sessionId);
            fwrite($socket, $handshakePacket);
            $handshakeResponse = fread($socket, 2048);

            if (!is_string($handshakeResponse) || strlen($handshakeResponse) < 6) {
                fclose($socket);

                return ['players' => []];
            }

            $challengeToken = (int) trim(str_replace("\x00", '', substr($handshakeResponse, 5)));
            $statPacket = "\xFE\xFD\x00" . pack('N', $sessionId) . pack('N', $challengeToken) . "\x00\x00\x00\x00";

            fwrite($socket, $statPacket);
            $response = fread($socket, 8192);
            fclose($socket);

            if (!is_string($response) || strlen($response) < 12) {
                return ['players' => []];
            }

            $payload = substr($response, 5);
            $parts = explode("player_\x00\x00", $payload, 2);
            if (count($parts) < 2) {
                return ['players' => []];
            }

            $playersChunk = $parts[1];
            $names = array_values(array_filter(array_map('trim', explode("\x00", $playersChunk)), function (string $name): bool {
                return $name !== '';
            }));

            return [
                'players' => array_map(fn (string $name) => ['name' => $name, 'uuid' => '', 'ping' => 0], $names),
            ];
        } catch (Throwable $exception) {
            return ['players' => []];
        }
    }

    /**
     * @return array{connected: bool, online: int, players: array<int, array{name: string, uuid: string, ping: int}>, latency_ms: int}
     */
    private function statusPlayers(string $host, int $port): array
    {
        $connected = false;
        $online = 0;
        $players = [];
        $latencyMs = 0;

        try {
            $socket = @stream_socket_client(
                sprintf('tcp://%s:%d', $host, $port),
                $errno,
                $error,
                0.9,
                STREAM_CLIENT_CONNECT
            );

            if (!is_resource($socket)) {
                return ['connected' => false, 'online' => 0, 'players' => [], 'latency_ms' => 0];
            }

            stream_set_timeout($socket, 1, 500000);
            $connected = true;

            $start = microtime(true);

            $handshakeData = $this->encodeVarInt(0x00)
                . $this->encodeVarInt(754)
                . $this->encodeString($host)
                . pack('n', $port)
                . $this->encodeVarInt(1);
            fwrite($socket, $this->encodeVarInt(strlen($handshakeData)) . $handshakeData);
            fwrite($socket, $this->encodeVarInt(1) . $this->encodeVarInt(0));

            $packetLength = $this->readVarInt($socket);
            if ($packetLength === null || $packetLength <= 0) {
                fclose($socket);

                return ['connected' => true, 'online' => 0, 'players' => [], 'latency_ms' => 0];
            }

            $packetBody = $this->readBytes($socket, $packetLength);
            fclose($socket);

            if ($packetBody === null) {
                return ['connected' => true, 'online' => 0, 'players' => [], 'latency_ms' => 0];
            }

            $latencyMs = (int) round((microtime(true) - $start) * 1000);

            $offset = 0;
            $packetId = $this->readVarIntFromString($packetBody, $offset);
            if ($packetId !== 0x00) {
                return ['connected' => true, 'online' => 0, 'players' => [], 'latency_ms' => $latencyMs];
            }

            $jsonLength = $this->readVarIntFromString($packetBody, $offset);
            if ($jsonLength === null || $jsonLength < 0) {
                return ['connected' => true, 'online' => 0, 'players' => [], 'latency_ms' => $latencyMs];
            }

            $json = substr($packetBody, $offset, $jsonLength);
            if (!is_string($json) || $json === '') {
                return ['connected' => true, 'online' => 0, 'players' => [], 'latency_ms' => $latencyMs];
            }

            $decoded = json_decode($json, true);
            if (!is_array($decoded)) {
                return ['connected' => true, 'online' => 0, 'players' => [], 'latency_ms' => $latencyMs];
            }

            $online = max(0, (int) (($decoded['players']['online'] ?? 0)));

            $sample = $decoded['players']['sample'] ?? [];
            if (is_array($sample)) {
                foreach ($sample as $entry) {
                    if (!is_array($entry)) {
                        continue;
                    }

                    $name = trim((string) ($entry['name'] ?? ''));
                    if ($name === '') {
                        continue;
                    }

                    $players[] = [
                        'name' => $name,
                        'uuid' => $this->normalizeUuid((string) ($entry['id'] ?? '')),
                        'ping' => $latencyMs,
                    ];
                }
            }
        } catch (Throwable $exception) {
            return ['connected' => false, 'online' => 0, 'players' => [], 'latency_ms' => 0];
        }

        return [
            'connected' => $connected,
            'online' => $online,
            'players' => $players,
            'latency_ms' => $latencyMs,
        ];
    }

    /**
     * @return array<int, array{name: string, uuid: string}>
     */
    private function readOps(Server $server): array
    {
        $raw = $this->readJsonArrayFile($server, '/ops.json');

        $result = [];
        foreach ($raw as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $name = trim((string) ($entry['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $result[] = [
                'name' => $name,
                'uuid' => $this->normalizeUuid((string) ($entry['uuid'] ?? '')),
            ];
        }

        return $result;
    }

    /**
     * @return array<int, array{name: string, uuid: string}>
     */
    private function readBanned(Server $server): array
    {
        $raw = $this->readJsonArrayFile($server, '/banned-players.json');

        $result = [];
        foreach ($raw as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $name = trim((string) ($entry['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $result[] = [
                'name' => $name,
                'uuid' => $this->normalizeUuid((string) ($entry['uuid'] ?? '')),
            ];
        }

        return $result;
    }

    /**
     * @return array<int, mixed>
     */
    private function readJsonArrayFile(Server $server, string $path): array
    {
        try {
            $content = $this->fileRepository
                ->setServer($server)
                ->getContent($path, 1024 * 1024);
            $decoded = json_decode($content, true);

            return is_array($decoded) ? $decoded : [];
        } catch (Throwable $exception) {
            return [];
        }
    }

    /**
     * @param array<int, array{name: string, uuid: string}> $entries
     */
    private function isInByNameOrUuid(array $entries, string $name, string $uuid): bool
    {
        $lookupName = mb_strtolower($name);

        foreach ($entries as $entry) {
            if ($uuid !== '' && $uuid === $this->normalizeUuid($entry['uuid'] ?? '')) {
                return true;
            }

            if ($lookupName !== '' && $lookupName === mb_strtolower((string) ($entry['name'] ?? ''))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function actionGroups(): array
    {
        return [
            [
                'id' => 'general',
                'title' => 'General Actions',
                'description' => 'Send standard moderation commands to server console.',
                'actions' => [
                    [
                        'id' => 'message',
                        'label' => 'Message',
                        'description' => 'Send a direct message to this player.',
                        'tone' => 'primary',
                        'command' => 'tell {{player}} {{text}}',
                        'requires_input' => true,
                        'input_key' => 'text',
                        'input_label' => 'Message',
                        'input_placeholder' => 'Type message to send',
                    ],
                    [
                        'id' => 'teleport',
                        'label' => 'Teleport',
                        'description' => 'Teleport player to world spawn.',
                        'tone' => 'neutral',
                        'command' => 'tp {{player}} 0 80 0',
                    ],
                    [
                        'id' => 'kick',
                        'label' => 'Kick',
                        'description' => 'Kick player from server.',
                        'tone' => 'warning',
                        'command' => 'kick {{player}} {{reason}}',
                        'requires_input' => true,
                        'input_key' => 'reason',
                        'input_label' => 'Reason',
                        'input_placeholder' => 'Reason for kick',
                    ],
                    [
                        'id' => 'ban',
                        'label' => 'Ban',
                        'description' => 'Ban player from server.',
                        'tone' => 'danger',
                        'command' => 'ban {{player}} {{reason}}',
                        'requires_input' => true,
                        'input_key' => 'reason',
                        'input_label' => 'Reason',
                        'input_placeholder' => 'Reason for ban',
                    ],
                ],
            ],
            [
                'id' => 'minecraft',
                'title' => 'Minecraft Actions',
                'description' => 'Quick gameplay tools for Java servers.',
                'actions' => [
                    [
                        'id' => 'minecraft.gamemode',
                        'label' => 'Gamemode',
                        'description' => 'Switch player gamemode.',
                        'tone' => 'success',
                        'command' => 'gamemode {{mode}} {{player}}',
                        'requires_input' => true,
                        'input_key' => 'mode',
                        'input_label' => 'Mode',
                        'input_placeholder' => 'survival|creative|adventure|spectator',
                    ],
                    [
                        'id' => 'minecraft.heal',
                        'label' => 'Heal',
                        'description' => 'Restore health immediately.',
                        'tone' => 'success',
                        'command' => 'effect give {{player}} minecraft:instant_health 1 1 true',
                    ],
                    [
                        'id' => 'minecraft.kill',
                        'label' => 'Kill',
                        'description' => 'Force player death command.',
                        'tone' => 'danger',
                        'command' => 'kill {{player}}',
                    ],
                    [
                        'id' => 'minecraft.effect',
                        'label' => 'Effect',
                        'description' => 'Apply potion effect.',
                        'tone' => 'neutral',
                        'command' => 'effect give {{player}} {{effect}} 60 1 true',
                        'requires_input' => true,
                        'input_key' => 'effect',
                        'input_label' => 'Effect ID',
                        'input_placeholder' => 'minecraft:speed',
                    ],
                ],
            ],
            [
                'id' => 'inventory',
                'title' => 'Inventory Management',
                'description' => 'Inventory-related commands.',
                'actions' => [
                    [
                        'id' => 'inventory.give',
                        'label' => 'Give Item',
                        'description' => 'Give item to player inventory.',
                        'tone' => 'primary',
                        'command' => 'give {{player}} {{item}} {{amount}}',
                        'requires_input' => true,
                        'input_key' => 'item',
                        'input_label' => 'Item ID',
                        'input_placeholder' => 'minecraft:diamond 1',
                    ],
                    [
                        'id' => 'inventory.clear',
                        'label' => 'Clear Inventory',
                        'description' => 'Remove all carried items.',
                        'tone' => 'warning',
                        'command' => 'clear {{player}}',
                    ],
                    [
                        'id' => 'inventory.enderchest',
                        'label' => 'Inspect Ender Chest',
                        'description' => 'Inspect Ender Chest data in console.',
                        'tone' => 'neutral',
                        'command' => 'data get entity {{player}} EnderItems',
                    ],
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed>|null $profile
     * @return array<int, array<string, mixed>>
     */
    private function actionGroupsForProfile(?array $profile): array
    {
        $groups = $this->actionGroups();
        if (!$profile) {
            return $groups;
        }

        $isBanned = (bool) ($profile['banned'] ?? false);
        foreach ($groups as &$group) {
            if (!isset($group['actions']) || !is_array($group['actions'])) {
                continue;
            }

            foreach ($group['actions'] as &$action) {
                if (!is_array($action) || (string) ($action['id'] ?? '') !== 'ban') {
                    continue;
                }

                if ($isBanned) {
                    $action = [
                        'id' => 'unban',
                        'label' => 'Unban',
                        'description' => 'Remove ban and allow this player to reconnect.',
                        'tone' => 'success',
                        'command' => 'pardon {{player}}',
                    ];
                }
            }
            unset($action);
        }
        unset($group);

        return $groups;
    }

    private function idFromName(string $name): string
    {
        $trimmed = trim($name);
        if ($trimmed === '') {
            return 'player';
        }

        return mb_strtolower(preg_replace('/\s+/', '-', $trimmed) ?? $trimmed);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveProfilePlayer(Server $server, string $playerId): ?array
    {
        $requested = trim($playerId);
        if ($requested === '') {
            return null;
        }

        $players = $this->players($server);
        $requestedLower = mb_strtolower($requested);
        $requestedUuid = $this->normalizeUuid($requested);

        foreach ($players as $player) {
            if ((string) ($player['id'] ?? '') === $requested) {
                return $player;
            }
        }

        if ($requestedUuid !== '') {
            foreach ($players as $player) {
                $playerUuid = $this->normalizeUuid((string) ($player['uuid'] ?? ''));
                if ($playerUuid !== '' && $playerUuid === $requestedUuid) {
                    return $player;
                }

                $playerSource = $this->normalizeUuid((string) ($player['source_id'] ?? ''));
                if ($playerSource !== '' && $playerSource === $requestedUuid) {
                    return $player;
                }
            }
        }

        foreach ($players as $player) {
            $playerName = trim((string) ($player['name'] ?? ''));
            $nameId = $this->idFromName($playerName);

            if (mb_strtolower((string) ($player['id'] ?? '')) === $requestedLower) {
                return $player;
            }

            if (mb_strtolower((string) ($player['source_id'] ?? '')) === $requestedLower) {
                return $player;
            }

            if ($playerName !== '' && mb_strtolower($playerName) === $requestedLower) {
                return $player;
            }

            if ($nameId !== '' && $nameId === $requestedLower) {
                return $player;
            }
        }

        if ($requestedUuid !== '') {
            $knownName = trim((string) ($this->knownPlayerNames($server)['uuid:' . $requestedUuid] ?? ''));
            if ($knownName !== '') {
                $knownLower = mb_strtolower($knownName);
                $knownId = $this->idFromName($knownName);

                foreach ($players as $player) {
                    $playerName = mb_strtolower(trim((string) ($player['name'] ?? '')));
                    $playerIdValue = mb_strtolower(trim((string) ($player['id'] ?? '')));

                    if ($playerName === $knownLower || $playerIdValue === $knownId) {
                        return $player;
                    }
                }
            }
        }

        return null;
    }

    /**
     * @param array<string, string> $knownNames
     */
    private function resolveDisplayName(
        string $rawName,
        string $uuid,
        array $knownNames,
        string $fallbackName = ''
    ): string
    {
        $name = trim($rawName);
        if ($name !== '' && !$this->isGenericPlayerName($name)) {
            return $name;
        }

        if ($uuid !== '') {
            $cached = trim((string) ($knownNames['uuid:' . $uuid] ?? ''));
            if ($cached !== '') {
                return $cached;
            }

            return 'Player ' . substr($uuid, 0, 8);
        }

        $fallback = trim($fallbackName);
        if ($fallback !== '' && !$this->isGenericPlayerName($fallback)) {
            return $fallback;
        }

        if ($name !== '' && !$this->isAnonymousLikePlayerName($name)) {
            return $name;
        }

        if ($name !== '') {
            return '';
        }

        return '';
    }

    /**
     * @param array<string, string> $knownNames
     */
    private function rememberPlayerName(array &$knownNames, string $uuid, string $name): void
    {
        $trimmed = trim($name);
        if ($uuid === '' || $trimmed === '' || $this->isGenericPlayerName($trimmed)) {
            return;
        }

        $knownNames['uuid:' . $uuid] = $trimmed;
    }

    private function shouldReplaceDisplayName(string $currentName, string $newName): bool
    {
        $current = trim($currentName);
        $candidate = trim($newName);

        if ($candidate === '') {
            return false;
        }

        if ($current === '') {
            return true;
        }

        return $this->isGenericPlayerName($current) && !$this->isGenericPlayerName($candidate);
    }

    private function isGenericPlayerName(string $name): bool
    {
        $normalized = mb_strtolower(trim($name));
        if ($normalized === '') {
            return true;
        }

        if ((bool) preg_match('/^online\s+player\s*#?\d+$/iu', $normalized)) {
            return true;
        }

        return $this->isAnonymousLikePlayerName($normalized);
    }

    private function isAnonymousLikePlayerName(string $name): bool
    {
        $normalized = mb_strtolower(trim($name));

        return in_array($normalized, ['anonymous', 'anonymous player', 'unknown', 'unknown player'], true);
    }

    /**
     * @return array<string, string>
     */
    private function knownPlayerNames(Server $server): array
    {
        if (isset($this->knownPlayerNamesCache[$server->id])) {
            return $this->knownPlayerNamesCache[$server->id];
        }

        $raw = Cache::get($this->knownPlayerNamesCacheKey($server), []);
        $normalized = [];

        if (is_array($raw)) {
            foreach ($raw as $key => $value) {
                if (!is_string($key) || !is_string($value)) {
                    continue;
                }

                $name = trim($value);
                if ($name === '' || $this->isGenericPlayerName($name)) {
                    continue;
                }

                $normalized[$key] = $name;
            }
        }

        $this->knownPlayerNamesCache[$server->id] = $normalized;

        return $normalized;
    }

    /**
     * @param array<string, string> $knownNames
     */
    private function persistKnownPlayerNames(Server $server, array $knownNames): void
    {
        $this->knownPlayerNamesCache[$server->id] = $knownNames;
        Cache::put($this->knownPlayerNamesCacheKey($server), $knownNames, now()->addDays(3));
    }

    private function knownPlayerNamesCacheKey(Server $server): string
    {
        return 'players:mcjava:known-names:' . $server->id;
    }

    /**
     * @return array<int, string>
     */
    private function lastOnlineNames(Server $server): array
    {
        if (isset($this->lastOnlineNamesCache[$server->id])) {
            return $this->lastOnlineNamesCache[$server->id];
        }

        $raw = Cache::get($this->lastOnlineNamesCacheKey($server), []);
        $normalized = [];

        if (is_array($raw)) {
            foreach ($raw as $index => $name) {
                if (!is_numeric($index) || !is_string($name)) {
                    continue;
                }

                $trimmed = trim($name);
                if ($trimmed === '' || $this->isGenericPlayerName($trimmed)) {
                    continue;
                }

                $normalized[(int) $index] = $trimmed;
            }
        }

        $this->lastOnlineNamesCache[$server->id] = $normalized;

        return $normalized;
    }

    /**
     * @param array<int, string> $names
     */
    private function persistLastOnlineNames(Server $server, array $names): void
    {
        $this->lastOnlineNamesCache[$server->id] = $names;
        Cache::put($this->lastOnlineNamesCacheKey($server), $names, now()->addMinutes(45));
    }

    private function lastOnlineNamesCacheKey(Server $server): string
    {
        return 'players:mcjava:last-online-names:' . $server->id;
    }

    private function avatarFor(string $name, string $uuid): string
    {
        $officialUuid = $this->resolveOfficialUuidByName($name);
        if ($officialUuid !== '') {
            return sprintf('https://mc-heads.net/avatar/%s/64', rawurlencode($officialUuid));
        }

        $trimmedName = trim($name);
        if ($trimmedName === '') {
            $normalizedUuid = $this->normalizeUuid($uuid);

            return $normalizedUuid !== ''
                ? sprintf('https://mc-heads.net/avatar/%s/64', rawurlencode($normalizedUuid))
                : '';
        }

        return sprintf('https://mc-heads.net/avatar/%s/64', rawurlencode($trimmedName));
    }

    private function resolveOfficialUuidByName(string $name): string
    {
        $lookup = mb_strtolower(trim($name));
        if ($lookup === '') {
            return '';
        }

        if (array_key_exists($lookup, $this->officialUuidCacheByName)) {
            return $this->officialUuidCacheByName[$lookup];
        }

        $cacheKey = 'players:mcjava:mojang-uuid:' . md5($lookup);
        $cached = Cache::get($cacheKey);
        if (is_string($cached)) {
            $resolved = $cached === '__none__' ? '' : $this->normalizeUuid($cached);
            $this->officialUuidCacheByName[$lookup] = $resolved;

            return $resolved;
        }

        try {
            $response = Http::timeout(2)
                ->connectTimeout(2)
                ->acceptJson()
                ->get('https://api.mojang.com/users/profiles/minecraft/' . rawurlencode(trim($name)));

            if ($response->ok()) {
                $id = (string) ($response->json('id') ?? '');
                $resolved = $this->normalizeUuid($id);
                if ($resolved !== '') {
                    Cache::put($cacheKey, $resolved, now()->addHours(12));
                    $this->officialUuidCacheByName[$lookup] = $resolved;

                    return $resolved;
                }
            }
        } catch (Throwable) {
            // Network failures are expected occasionally; keep graceful fallback behavior.
        }

        Cache::put($cacheKey, '__none__', now()->addMinutes(30));
        $this->officialUuidCacheByName[$lookup] = '';

        return '';
    }

    private function offlineUuidForName(string $name): string
    {
        $trimmed = trim($name);
        if ($trimmed === '') {
            return '';
        }

        $hash = md5('OfflinePlayer:' . $trimmed);
        if (!is_string($hash) || strlen($hash) !== 32) {
            return '';
        }

        $bytes = hex2bin($hash);
        if ($bytes === false || strlen($bytes) !== 16) {
            return '';
        }

        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x30);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);

        return $this->normalizeUuid(bin2hex($bytes));
    }

    /**
     * @param array<string, mixed> $profile
     * @return array<string, mixed>|null
     */
    private function loadPlayerData(Server $server, array $profile): ?array
    {
        $cacheKey = $server->id . ':' . (string) ($profile['id'] ?? $profile['name'] ?? '');

        if (array_key_exists($cacheKey, $this->playerDataCache)) {
            return $this->playerDataCache[$cacheKey];
        }

        $name = (string) ($profile['name'] ?? '');
        $uuid = (string) ($profile['uuid'] ?? '');
        $candidateUuids = array_values(array_unique(array_filter([
            $this->normalizeUuid($uuid),
            $this->resolveOfficialUuidByName($name),
            $this->offlineUuidForName($name),
        ])));

        if (empty($candidateUuids)) {
            $this->playerDataCache[$cacheKey] = null;

            return null;
        }

        foreach ($this->candidateWorldDirectories($server) as $world) {
            $normalizedWorld = trim($world, '/');
            if ($normalizedWorld === '') {
                continue;
            }

            foreach ($candidateUuids as $candidateUuid) {
                $path = sprintf('/%s/playerdata/%s.dat', $normalizedWorld, $candidateUuid);

                try {
                    $raw = $this->fileRepository->setServer($server)->getContent($path, 2 * 1024 * 1024);
                } catch (Throwable) {
                    continue;
                }

                $nbt = $this->decodePlayerDataNbt($raw);
                if (!is_array($nbt)) {
                    continue;
                }

                $result = [
                    'world' => $normalizedWorld,
                    'uuid' => $candidateUuid,
                    'path' => $path,
                    'nbt' => $nbt,
                ];

                $this->playerDataCache[$cacheKey] = $result;

                return $result;
            }
        }

        $this->playerDataCache[$cacheKey] = null;

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function candidateWorldDirectories(Server $server): array
    {
        $properties = $this->serverProperties($server);
        $levelName = trim((string) ($properties['level-name'] ?? 'world'));

        $candidates = [$levelName, 'world'];
        $resolved = [];

        foreach ($candidates as $candidate) {
            $normalized = trim($candidate, '/');
            if ($normalized !== '') {
                $resolved[] = $normalized;
            }
        }

        return array_values(array_unique($resolved));
    }

    /**
     * @return array<string, string>
     */
    private function serverProperties(Server $server): array
    {
        if (isset($this->serverPropertiesCache[$server->id])) {
            return $this->serverPropertiesCache[$server->id];
        }

        try {
            $content = $this->fileRepository
                ->setServer($server)
                ->getContent('/server.properties', 512 * 1024);
        } catch (Throwable) {
            $this->serverPropertiesCache[$server->id] = [];

            return $this->serverPropertiesCache[$server->id];
        }

        $properties = [];
        foreach (preg_split('/\r\n|\r|\n/', $content) ?: [] as $line) {
            $trimmed = trim((string) $line);
            if ($trimmed === '' || str_starts_with($trimmed, '#') || str_starts_with($trimmed, '!')) {
                continue;
            }

            $position = strpos($trimmed, '=');
            if ($position === false) {
                continue;
            }

            $key = trim(substr($trimmed, 0, $position));
            $value = trim(substr($trimmed, $position + 1));
            if ($key !== '') {
                $properties[$key] = $value;
            }
        }

        $this->serverPropertiesCache[$server->id] = $properties;

        return $this->serverPropertiesCache[$server->id];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodePlayerDataNbt(string $raw): ?array
    {
        $payloads = [$raw];

        $gz = @gzdecode($raw);
        if (is_string($gz) && $gz !== '') {
            $payloads[] = $gz;
        }

        $zlib = @zlib_decode($raw);
        if (is_string($zlib) && $zlib !== '') {
            $payloads[] = $zlib;
        }

        if (strlen($raw) > 2) {
            $inflate = @gzinflate(substr($raw, 2));
            if (is_string($inflate) && $inflate !== '') {
                $payloads[] = $inflate;
            }
        }

        foreach (array_values(array_unique($payloads)) as $payload) {
            $parsed = MinecraftNbtReader::parseRootCompound($payload);
            if (is_array($parsed)) {
                return $parsed;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $nbt
     * @return array<int, array{slot:int, id:string, count:int}>
     */
    private function extractInventorySlots(array $nbt): array
    {
        $inventory = $nbt['Inventory'] ?? null;
        if (!is_array($inventory)) {
            return [];
        }

        $result = [];
        foreach ($inventory as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $slot = $this->numericTag($entry['Slot'] ?? null);
            if ($slot === null) {
                continue;
            }

            $itemId = trim((string) ($entry['id'] ?? ''));
            if ($itemId === '') {
                continue;
            }

            $result[] = [
                'slot' => (int) $slot,
                'id' => $itemId,
                'count' => $this->normalizeItemCount($entry['Count'] ?? 1),
            ];
        }

        return $result;
    }

    /**
     * @param array<int, array{slot:int, id:string, count:int}> $rawSlots
     * @return array<int, array<string, mixed>>
     */
    private function buildInventorySections(Server $server, array $rawSlots): array
    {
        $main = [];
        $hotbar = [];
        $helmet = null;
        $chestplate = null;
        $leggings = null;
        $boots = null;
        $offhand = null;

        foreach ($rawSlots as $item) {
            $slot = (int) ($item['slot'] ?? -999);

            if ($slot >= 0 && $slot <= 8) {
                $hotbar[] = $this->toInventorySlot($server, (string) ($slot + 1), $item);
                continue;
            }

            if ($slot >= 9 && $slot <= 35) {
                $main[] = $this->toInventorySlot($server, (string) $slot, $item);
                continue;
            }

            if ($slot === 103) {
                $helmet = $this->toInventorySlot($server, 'helmet', $item);
                continue;
            }

            if ($slot === 102) {
                $chestplate = $this->toInventorySlot($server, 'chestplate', $item);
                continue;
            }

            if ($slot === 101) {
                $leggings = $this->toInventorySlot($server, 'leggings', $item);
                continue;
            }

            if ($slot === 100) {
                $boots = $this->toInventorySlot($server, 'boots', $item);
                continue;
            }

            if ($slot === 150) {
                $offhand = $this->toInventorySlot($server, 'offhand', $item);
            }
        }

        usort($main, fn (array $a, array $b): int => ((int) $a['slot']) <=> ((int) $b['slot']));
        usort($hotbar, fn (array $a, array $b): int => ((int) $a['slot']) <=> ((int) $b['slot']));

        $armor = array_values(array_filter([
            $helmet,
            $chestplate,
            $leggings,
            $boots,
            $offhand,
        ]));

        return [
            [
                'id' => 'armor',
                'title' => 'Armor',
                'slots' => $armor,
            ],
            [
                'id' => 'inventory',
                'title' => 'Main Inventory',
                'slots' => $main,
            ],
            [
                'id' => 'hotbar',
                'title' => 'Hotbar',
                'slots' => $hotbar,
            ],
        ];
    }

    /**
     * @param array{slot:int, id:string, count:int} $item
     * @return array<string, mixed>
     */
    private function toInventorySlot(Server $server, string $slotLabel, array $item): array
    {
        $itemId = (string) ($item['id'] ?? '');
        $itemName = $this->itemDisplayName($itemId);

        return [
            'slot' => $slotLabel,
            'item_name' => $itemName,
            'item_id' => $itemId,
            'count' => (int) ($item['count'] ?? 1),
            'icon_url' => $this->itemIconUrl($server, $itemId),
        ];
    }

    private function itemDisplayName(string $itemId): string
    {
        $normalized = trim($itemId);
        if ($normalized === '') {
            return 'Unknown Item';
        }

        $base = str_contains($normalized, ':')
            ? substr($normalized, strpos($normalized, ':') + 1)
            : $normalized;

        $base = $base === false ? $normalized : $base;
        $label = str_replace(['_', '-'], ' ', $base);

        return ucwords($label);
    }

    private function itemIconUrl(Server $server, string $itemId): ?string
    {
        $normalized = trim($itemId);
        if ($normalized === '' || $normalized === 'minecraft:air') {
            return null;
        }

        $namespace = 'minecraft';
        $path = $normalized;
        if (str_contains($normalized, ':')) {
            [$ns, $itemPath] = explode(':', $normalized, 2);
            $namespace = strtolower(trim($ns));
            $path = trim($itemPath);
        }

        if ($namespace !== 'minecraft' || $path === '') {
            return $this->modItemIconDataUri($server, $namespace, $path);
        }

        return sprintf(
            'https://raw.githubusercontent.com/InventivetalentDev/minecraft-assets/1.21.4/assets/minecraft/textures/item/%s.png',
            rawurlencode($path)
        );
    }

    private function modItemIconDataUri(Server $server, string $namespace, string $path): ?string
    {
        if ($namespace === '' || $path === '') {
            return null;
        }

        $cacheKey = sprintf(
            'players:mcjava:mod-item-icon:v4:%d:%s:%s',
            $server->id,
            md5($namespace),
            md5($path)
        );

        $cached = Cache::get($cacheKey);
        if (is_string($cached)) {
            return $cached === '__none__' ? null : $cached;
        }

        $this->modTextureLookupHadTransientError = false;
        $binary = $this->findModTextureBinary($server, $namespace, $path);
        if (!is_string($binary) || $binary === '') {
            $fallbackUrl = $this->modItemIconFallbackUrl($namespace, $path);
            if (is_string($fallbackUrl) && $fallbackUrl !== '') {
                Cache::put($cacheKey, $fallbackUrl, now()->addHours(8));

                return $fallbackUrl;
            }

            if (!$this->modTextureLookupHadTransientError) {
                Cache::put($cacheKey, '__none__', now()->addMinutes(5));
            }

            return null;
        }

        $mime = 'image/png';
        if (str_starts_with($binary, "\xFF\xD8\xFF")) {
            $mime = 'image/jpeg';
        } elseif (str_starts_with($binary, "GIF8")) {
            $mime = 'image/gif';
        } elseif (str_starts_with($binary, "RIFF") && str_contains(substr($binary, 0, 16), 'WEBP')) {
            $mime = 'image/webp';
        }

        $dataUri = sprintf('data:%s;base64,%s', $mime, base64_encode($binary));
        Cache::put($cacheKey, $dataUri, now()->addHours(8));

        return $dataUri;
    }

    private function modItemIconFallbackUrl(string $namespace, string $path): ?string
    {
        $cleanPath = trim($path, '/');
        if ($cleanPath === '') {
            return null;
        }

        return match ($namespace) {
            'alexsmobs' => sprintf(
                'https://raw.githubusercontent.com/AlexModGuy/AlexsMobs/1.20/src/main/resources/assets/alexsmobs/textures/item/%s.png',
                rawurlencode($cleanPath)
            ),
            'sophisticatedbackpacks' => str_ends_with($cleanPath, 'backpack')
                ? 'https://raw.githubusercontent.com/P3pp3rF1y/SophisticatedBackpacks/1.20.x/src/main/resources/assets/sophisticatedbackpacks/textures/block/backpack_cloth.png'
                : sprintf(
                    'https://raw.githubusercontent.com/P3pp3rF1y/SophisticatedBackpacks/1.20.x/src/main/resources/assets/sophisticatedbackpacks/textures/item/%s.png',
                    rawurlencode($cleanPath)
                ),
            default => null,
        };
    }

    private function findModTextureBinary(Server $server, string $namespace, string $path): ?string
    {
        $candidates = $this->textureCandidatePaths($namespace, $path);
        if (empty($candidates)) {
            return null;
        }

        $startedAt = microtime(true);
        foreach ($this->candidateModJars($server, $namespace) as $jar) {
            if ((microtime(true) - $startedAt) > 3.2) {
                break;
            }

            $binary = $this->readModTextureFromJar($server, $jar['path'], $namespace, $path, $candidates);
            if (is_string($binary) && $binary !== '') {
                return $binary;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function textureCandidatePaths(string $namespace, string $path): array
    {
        $normalizedPath = trim($path, '/');
        if ($normalizedPath === '') {
            return [];
        }

        $list = [
            sprintf('assets/%s/textures/item/%s.png', $namespace, $normalizedPath),
            sprintf('assets/%s/textures/items/%s.png', $namespace, $normalizedPath),
            sprintf('assets/%s/textures/block/%s.png', $namespace, $normalizedPath),
            sprintf('assets/%s/textures/blocks/%s.png', $namespace, $normalizedPath),
        ];

        return array_values(array_unique($list));
    }

    /**
     * @return array<int, array{name:string,path:string,size:int}>
     */
    private function candidateModJars(Server $server, string $namespace): array
    {
        if (!isset($this->modsJarCache[$server->id])) {
            $this->modsJarCache[$server->id] = $this->listModJars($server);
        }

        $all = $this->modsJarCache[$server->id];
        if (empty($all)) {
            return [];
        }

        $needle = mb_strtolower(str_replace('_', '-', $namespace));

        $primary = array_values(array_filter($all, function (array $jar) use ($needle): bool {
            $haystack = mb_strtolower(str_replace('_', '-', (string) ($jar['name'] ?? '')));

            return str_contains($haystack, $needle);
        }));

        $hintTokens = $this->namespaceJarHintTokens($needle);
        $hinted = array_values(array_filter($all, function (array $jar) use ($hintTokens): bool {
            if (empty($hintTokens)) {
                return false;
            }

            $haystack = mb_strtolower(str_replace('_', '-', (string) ($jar['name'] ?? '')));
            foreach ($hintTokens as $token) {
                if ($token !== '' && str_contains($haystack, $token)) {
                    return true;
                }
            }

            return false;
        }));

        $fallback = array_values(array_filter($all, function (array $jar) use ($primary, $hinted): bool {
            foreach (array_merge($primary, $hinted) as $picked) {
                if (($picked['path'] ?? '') === ($jar['path'] ?? '')) {
                    return false;
                }
            }

            return true;
        }));

        usort($primary, fn (array $a, array $b): int => (($a['size'] ?? 0) <=> ($b['size'] ?? 0)));
        usort($hinted, fn (array $a, array $b): int => (($a['size'] ?? 0) <=> ($b['size'] ?? 0)));
        usort($fallback, fn (array $a, array $b): int => (($a['size'] ?? 0) <=> ($b['size'] ?? 0)));

        return array_merge($primary, $hinted, array_slice($fallback, 0, 8));
    }

    /**
     * @return array<int, string>
     */
    private function namespaceJarHintTokens(string $namespace): array
    {
        return match ($namespace) {
            'alexsmobs' => ['alexsmobs', 'beb', 'born'],
            'epicskills' => ['epicskills', 'epicfight-skilltree', 'epicfight', 'skilltree'],
            'sophisticatedbackpacks' => ['sophisticatedbackpacks', 'sophisticatedcore'],
            default => [],
        };
    }

    /**
     * @return array<int, array{name:string,path:string,size:int}>
     */
    private function listModJars(Server $server): array
    {
        try {
            $items = $this->fileRepository
                ->setServer($server)
                ->getDirectory('/mods');
        } catch (Throwable) {
            $this->modTextureLookupHadTransientError = true;

            return [];
        }

        $result = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $name = (string) ($item['name'] ?? '');
            $isFile = (bool) ($item['file'] ?? false);
            if (!$isFile || $name === '' || !str_ends_with(mb_strtolower($name), '.jar')) {
                continue;
            }

            $result[] = [
                'name' => $name,
                'path' => '/mods/' . $name,
                'size' => (int) ($item['size'] ?? 0),
            ];
        }

        return $result;
    }

    /**
     * @param array<int, string> $baseCandidates
     */
    private function readModTextureFromJar(
        Server $server,
        string $jarPath,
        string $namespace,
        string $itemPath,
        array $baseCandidates
    ): ?string {
        $jarBinary = null;
        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                $jarBinary = $this->fileRepository
                    ->setServer($server)
                    ->getContent($jarPath, 64 * 1024 * 1024);
                break;
            } catch (Throwable) {
                if ($attempt === 1) {
                    $this->modTextureLookupHadTransientError = true;

                    return null;
                }

                usleep(120000);
            }
        }

        if (!is_string($jarBinary) || $jarBinary === '') {
            return null;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'modjar_');
        if ($tmp === false) {
            return null;
        }

        try {
            if (file_put_contents($tmp, $jarBinary) === false) {
                return null;
            }

            $zip = new \ZipArchive();
            $opened = $zip->open($tmp);
            if ($opened !== true) {
                return null;
            }

            try {
                foreach ($baseCandidates as $entryName) {
                    $binary = $zip->getFromName($entryName);
                    if (is_string($binary) && $binary !== '') {
                        return $binary;
                    }
                }

                $itemModelRef = sprintf('%s:item/%s', $namespace, trim($itemPath, '/'));
                $binary = $this->resolveTextureBinaryFromModel($zip, $namespace, $itemModelRef);
                if (is_string($binary) && $binary !== '') {
                    return $binary;
                }
            } finally {
                $zip->close();
            }
        } finally {
            @unlink($tmp);
        }

        return null;
    }

    private function resolveTextureBinaryFromModel(
        \ZipArchive $zip,
        string $defaultNamespace,
        string $modelReference,
        int $depth = 0,
        array $visited = []
    ): ?string {
        if ($depth > 6) {
            return null;
        }

        $normalizedRef = trim($modelReference);
        if ($normalizedRef === '' || in_array($normalizedRef, $visited, true)) {
            return null;
        }
        $visited[] = $normalizedRef;

        [$namespace, $modelPath] = $this->splitNamespacedReference($defaultNamespace, $normalizedRef);
        if ($namespace === '' || $modelPath === '') {
            return null;
        }

        $modelFile = sprintf('assets/%s/models/%s.json', $namespace, trim($modelPath, '/'));
        $modelRaw = $zip->getFromName($modelFile);
        if (!is_string($modelRaw) || $modelRaw === '') {
            return null;
        }

        $decoded = json_decode($modelRaw, true);
        if (!is_array($decoded)) {
            return null;
        }

        $textures = is_array($decoded['textures'] ?? null) ? $decoded['textures'] : [];
        $textureRefs = [];
        foreach ($textures as $value) {
            if (is_string($value) && $value !== '') {
                $resolved = $this->resolveTextureAlias($textures, $value);
                if ($resolved !== '') {
                    $textureRefs[] = $resolved;
                }
            }
        }

        foreach ($decoded as $key => $value) {
            if (!is_string($key) || !is_string($value)) {
                continue;
            }

            if (!str_contains(strtolower($key), 'texture')) {
                continue;
            }

            $resolved = $this->resolveTextureAlias($textures, trim($value));
            if ($resolved !== '') {
                $textureRefs[] = $resolved;
            }
        }

        foreach (array_values(array_unique($textureRefs)) as $textureRef) {
            $texturePath = $this->texturePathFromModelReference($namespace, $textureRef);
            if ($texturePath === '') {
                continue;
            }

            $binary = $zip->getFromName($texturePath);
            if (is_string($binary) && $binary !== '') {
                return $binary;
            }
        }

        $loader = $decoded['loader'] ?? null;
        if (is_string($loader) && str_contains($loader, ':')) {
            $loaderKey = strtolower(trim($loader));
            if ($loaderKey === 'sophisticatedbackpacks:backpack' && !str_ends_with($modelPath, '_base')) {
                $baseModelRef = $namespace . ':' . $modelPath . '_base';
                $binary = $this->resolveTextureBinaryFromModel($zip, $namespace, $baseModelRef, $depth + 1, $visited);
                if (is_string($binary) && $binary !== '') {
                    return $binary;
                }
            }
        }

        $parent = $decoded['parent'] ?? null;
        if (is_string($parent) && $parent !== '') {
            return $this->resolveTextureBinaryFromModel($zip, $namespace, $parent, $depth + 1, $visited);
        }

        return null;
    }

    /**
     * @param array<string, mixed> $textures
     */
    private function resolveTextureAlias(array $textures, string $value): string
    {
        $current = trim($value);
        $guard = 0;

        while (str_starts_with($current, '#') && $guard < 8) {
            $guard++;
            $key = substr($current, 1);
            $next = $textures[$key] ?? null;
            if (!is_string($next) || $next === '') {
                return '';
            }

            $current = trim($next);
        }

        return str_starts_with($current, '#') ? '' : $current;
    }

    /**
     * @return array{0:string,1:string}
     */
    private function splitNamespacedReference(string $defaultNamespace, string $reference): array
    {
        $namespace = strtolower(trim($defaultNamespace));
        $path = trim($reference);

        if (str_contains($path, ':')) {
            [$ns, $refPath] = explode(':', $path, 2);
            $namespace = strtolower(trim($ns));
            $path = trim($refPath);
        }

        return [$namespace, trim($path, '/')];
    }

    private function texturePathFromModelReference(string $defaultNamespace, string $reference): string
    {
        $ref = trim($reference);
        if ($ref === '') {
            return '';
        }

        $namespace = $defaultNamespace;
        $path = $ref;
        if (str_contains($ref, ':')) {
            [$ns, $p] = explode(':', $ref, 2);
            $namespace = strtolower(trim($ns));
            $path = trim($p);
        }

        $path = trim($path, '/');
        if ($path === '') {
            return '';
        }

        if (!str_ends_with($path, '.png')) {
            $path .= '.png';
        }

        return sprintf('assets/%s/textures/%s', $namespace, $path);
    }

    private function normalizeItemCount(mixed $value): int
    {
        $numeric = $this->numericTag($value);
        if ($numeric === null) {
            return 1;
        }

        $count = (int) $numeric;
        if ($count < 0) {
            $count += 256;
        }

        if ($count <= 0) {
            return 1;
        }

        return $count;
    }

    /**
     * @param array<string, mixed> $nbt
     */
    private function resolveGamemode(array $nbt): string
    {
        $raw = $this->numericTag($nbt['playerGameType'] ?? null);
        if ($raw === null) {
            $raw = $this->numericTag($nbt['PlayerGameType'] ?? null);
        }

        $mode = $raw !== null ? (int) $raw : -1;

        return match ($mode) {
            0 => 'Survival',
            1 => 'Creative',
            2 => 'Adventure',
            3 => 'Spectator',
            default => '-',
        };
    }

    /**
     * @param array<string, mixed> $profile
     * @param array<string, mixed> $nbt
     */
    private function effectiveGamemode(Server $server, array $profile, array $nbt): string
    {
        $detected = $this->resolveGamemode($nbt);
        $cacheKey = $this->gamemodeCacheKey($server, $profile);

        if ($detected !== '-') {
            Cache::put($cacheKey, $detected, now()->addHours(6));

            return $detected;
        }

        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        return '-';
    }

    /**
     * @param array<string, mixed> $profile
     */
    private function gamemodeCacheKey(Server $server, array $profile): string
    {
        $uuid = $this->normalizeUuid((string) ($profile['uuid'] ?? ''));
        $name = mb_strtolower(trim((string) ($profile['name'] ?? '')));
        $identity = $uuid !== '' ? $uuid : $name;

        return sprintf('players:mcjava:gamemode:v1:%d:%s', $server->id, md5($identity));
    }

    private function gamemodeLabelFromInput(string $value): string
    {
        $normalized = mb_strtolower(trim($value));

        return match ($normalized) {
            '0', 'survival' => 'Survival',
            '1', 'creative' => 'Creative',
            '2', 'adventure' => 'Adventure',
            '3', 'spectator' => 'Spectator',
            default => '-',
        };
    }

    private function numericTag(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (float) $value;
        }

        return null;
    }

    private function normalizeUuid(string $value): string
    {
        $value = strtolower(trim($value));
        if ($value === '') {
            return '';
        }

        $compact = preg_replace('/[^a-f0-9]/', '', $value) ?? '';
        if (strlen($compact) !== 32) {
            return '';
        }

        // Some status responders emit zero UUID as placeholder; treat it as missing.
        if ($compact === str_repeat('0', 32)) {
            return '';
        }

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($compact, 0, 8),
            substr($compact, 8, 4),
            substr($compact, 12, 4),
            substr($compact, 16, 4),
            substr($compact, 20, 12)
        );
    }

    private function encodeVarInt(int $value): string
    {
        $buffer = '';

        do {
            $byte = $value & 0x7F;
            $value >>= 7;
            if ($value !== 0) {
                $byte |= 0x80;
            }
            $buffer .= chr($byte);
        } while ($value !== 0);

        return $buffer;
    }

    private function encodeString(string $value): string
    {
        return $this->encodeVarInt(strlen($value)) . $value;
    }

    private function readVarInt($stream): ?int
    {
        $value = 0;
        $position = 0;

        while ($position < 5) {
            $char = fread($stream, 1);
            if (!is_string($char) || $char === '') {
                return null;
            }

            $byte = ord($char);
            $value |= ($byte & 0x7F) << ($position * 7);
            $position++;

            if (($byte & 0x80) !== 0x80) {
                return $value;
            }
        }

        return null;
    }

    private function readVarIntFromString(string $data, int &$offset): ?int
    {
        $value = 0;
        $position = 0;
        $length = strlen($data);

        while ($offset < $length && $position < 5) {
            $byte = ord($data[$offset]);
            $offset++;

            $value |= ($byte & 0x7F) << ($position * 7);
            $position++;

            if (($byte & 0x80) !== 0x80) {
                return $value;
            }
        }

        return null;
    }

    private function readBytes($stream, int $length): ?string
    {
        $buffer = '';
        while (strlen($buffer) < $length) {
            $chunk = fread($stream, $length - strlen($buffer));
            if (!is_string($chunk) || $chunk === '') {
                return null;
            }

            $buffer .= $chunk;
        }

        return $buffer;
    }
}
