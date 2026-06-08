<?php

namespace Pterodactyl\Services\Servers\Players\Providers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Pterodactyl\Models\Discord\ServerPlayerSnapshot;
use Pterodactyl\Models\Server;
use Pterodactyl\Repositories\Wings\DaemonCommandRepository;
use Pterodactyl\Services\Servers\Players\Contracts\PlayerProviderInterface;
use Pterodactyl\Services\Servers\Players\GameType;
use Pterodactyl\Services\Servers\Players\PlayerScope;
use Throwable;

class AgentPlayerSnapshotProvider implements PlayerProviderInterface
{
    public function __construct(private string $type = GameType::GENERIC)
    {
    }

    public function gameType(): string
    {
        return $this->type;
    }

    public function gameLabel(): string
    {
        return GameType::label($this->type) . ' Discord Agent';
    }

    public function capabilities(Server $server): array
    {
        $agentCapabilities = $server->discordAgent?->capabilities ?? [];

        return [
            'filters' => [
                [
                    'id' => PlayerScope::ALL,
                    'label' => 'All Players',
                    'description' => 'All online and offline players known by the agent.',
                ],
                [
                    'id' => PlayerScope::ONLINE,
                    'label' => 'Online Players',
                    'description' => 'Players currently connected according to the agent.',
                ],
                [
                    'id' => PlayerScope::OPERATORS,
                    'label' => 'Operators',
                    'description' => 'Players with operator privileges.',
                ],
                [
                    'id' => PlayerScope::ADMINS,
                    'label' => 'Admins',
                    'description' => 'Players detected as admin or staff by the agent adapter.',
                ],
                [
                    'id' => PlayerScope::BANNED,
                    'label' => 'Banned',
                    'description' => 'Players currently banned.',
                ],
            ],
            'action_groups' => $this->actionGroups($agentCapabilities),
            'tabs' => array_values(array_filter([
                'overview',
                ($this->isMinecraftJava() || ($agentCapabilities['inventory'] ?? false)) ? 'inventory' : null,
                ($this->isMinecraftJava() || ($agentCapabilities['statistics'] ?? false)) ? 'statistics' : null,
            ])),
            'notes' => [
                'Synced by Discord Agent. Offline players remain available from the latest agent snapshot.',
            ],
            'integrations' => [
                'discord_agent' => [
                    'enabled' => true,
                    'adapter' => $server->discordAgent?->adapter,
                    'connection_status' => $server->discordAgent?->connection_status,
                    'last_seen_at' => optional($server->discordAgent?->last_seen_at)->toAtomString(),
                ],
            ],
            'state' => [
                'source' => 'agent',
                'label' => 'Synced by Discord Agent',
            ],
        ];
    }

    public function counts(Server $server): array
    {
        if ($this->isMinecraftJava()) {
            $players = $this->mergedMinecraftPlayers($server, PlayerScope::ALL);

            return [
                'total' => count($players),
                'online' => count(array_filter($players, fn (array $player) => ($player['status'] ?? 'offline') === 'online')),
                'operators' => count(array_filter($players, fn (array $player) => (bool) ($player['is_operator'] ?? false))),
                'admins' => count(array_filter($players, fn (array $player) => (bool) ($player['is_admin'] ?? false))),
                'staff' => count(array_filter($players, fn (array $player) => (bool) ($player['is_operator'] ?? false) || in_array((string) ($player['role'] ?? ''), ['admin', 'staff', 'moderator', 'operator'], true))),
                'banned' => count(array_filter($players, fn (array $player) => (bool) ($player['banned'] ?? false))),
            ];
        }

        $query = $server->playerSnapshots();

        return [
            'total' => (clone $query)->count(),
            'online' => (clone $query)->where('status', 'online')->count(),
            'operators' => (clone $query)->where('is_operator', true)->count(),
            'admins' => (clone $query)->where('is_admin', true)->count(),
            'staff' => (clone $query)->whereIn('role', ['admin', 'staff', 'moderator'])->count(),
            'banned' => (clone $query)->where('banned', true)->count(),
        ];
    }

    public function list(Server $server, string $scope, ?string $search = null): array
    {
        if ($this->isMinecraftJava()) {
            return $this->mergedMinecraftPlayers($server, $scope, $search);
        }

        return $this->query($server, $scope, $search)
            ->orderByRaw("status = 'online' DESC")
            ->orderByDesc('last_seen_at')
            ->limit(250)
            ->get()
            ->map(fn (ServerPlayerSnapshot $player) => $this->transform($player))
            ->values()
            ->all();
    }

    public function profile(Server $server, string $playerId): ?array
    {
        $player = $server->playerSnapshots()
            ->where(fn (Builder $query) => $query
                ->where('player_id', $playerId)
                ->orWhere('uuid', $playerId)
                ->orWhere('name', $playerId)
            )
            ->first();

        if ($player) {
            $live = $this->minecraftLiveProfile($server, $player);
            $profile = $live ? $this->mergePlayerRecords($live, $this->transform($player)) : $this->transform($player);
            $profile = $this->withMinecraftGamemode($server, $profile);

            return [
                ...$profile,
                'action_groups' => $this->actionGroupsForProfileData($profile, $server->discordAgent?->capabilities ?? []),
            ];
        }

        if ($this->isMinecraftJava()) {
            $live = $this->minecraftProvider()?->profile($server, $playerId);
            $live = $live ? $this->withMinecraftGamemode($server, $live) : null;

            return $live ? [
                ...$live,
                'action_groups' => $this->actionGroupsForProfileData($live, $server->discordAgent?->capabilities ?? []),
            ] : null;
        }

        return null;
    }

    public function inventory(Server $server, string $playerId): array
    {
        if ($this->isMinecraftJava()) {
            $delegated = $this->delegateMinecraftInventory($server, $playerId);
            if ($delegated) {
                return $delegated;
            }
        }

        return [
            'available' => false,
            'message' => $this->isMinecraftJava()
                ? 'Inventory needs a Mojang UUID/playerdata file. Ask the player to join once with a verified profile, then retry.'
                : 'Inventory data will appear after the agent adapter sends a snapshot for this player.',
            'sections' => [],
            'summary' => [],
            'player_id' => $playerId,
            'is_dummy' => false,
        ];
    }

    public function statistics(Server $server, string $playerId): array
    {
        if ($this->isMinecraftJava()) {
            $delegated = $this->delegateMinecraftStatistics($server, $playerId);
            if ($delegated) {
                return $delegated;
            }
        }

        $player = $this->resolveSnapshot($server, $playerId);

        return [
            'available' => true,
            'message' => $this->isMinecraftJava()
                ? 'Limited statistics are synced from the Discord Agent snapshot.'
                : 'Statistics data will appear after the agent adapter sends a snapshot for this player.',
            'categories' => $player ? $this->snapshotStatistics($player) : [],
            'player_id' => $playerId,
            'is_dummy' => false,
        ];
    }

    public function performAction(Server $server, string $playerId, string $action, array $context = []): array
    {
        $player = $this->resolveActionPlayer($server, $playerId);
        if (!$player) {
            return [
                'accepted' => false,
                'queued' => false,
                'message' => 'Player not found.',
                'action' => $action,
                'player_id' => $playerId,
                'is_dummy' => false,
            ];
        }

        $selected = $this->actionMap($player, $server->discordAgent?->capabilities ?? [])[$action] ?? null;
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

        $command = $this->buildCommand($selected, $player, $context);
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
            app(DaemonCommandRepository::class)->setServer($server)->send($command);

            if ($this->isMinecraftJava() && $action === 'minecraft.gamemode') {
                $this->rememberMinecraftGamemode($server, $player, (string) ($context['mode'] ?? ''));
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
        } catch (Throwable) {
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

    private function query(Server $server, string $scope, ?string $search = null): Builder
    {
        $query = $server->playerSnapshots()->getQuery();

        match ($scope) {
            PlayerScope::ONLINE => $query->where('status', 'online'),
            PlayerScope::OPERATORS => $query->where('is_operator', true),
            PlayerScope::ADMINS, PlayerScope::STAFF => $query->where(fn (Builder $q) => $q
                ->where('is_admin', true)
                ->orWhereIn('role', ['admin', 'staff', 'moderator'])
            ),
            PlayerScope::BANNED => $query->where('banned', true),
            default => null,
        };

        $search = trim((string) $search);
        if ($search !== '') {
            $query->where(fn (Builder $q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('uuid', 'like', "%{$search}%")
                ->orWhere('player_id', 'like', "%{$search}%")
                ->orWhere('discord_user_id', 'like', "%{$search}%")
            );
        }

        return $query;
    }

    private function transform(ServerPlayerSnapshot $player): array
    {
        return [
            'id' => $player->player_id,
            'name' => $player->name,
            'uuid' => $player->uuid ?? $player->player_id,
            'source_id' => 'discord_agent',
            'status' => $player->status,
            'ping' => $player->ping,
            'role' => $player->role,
            'is_operator' => $player->is_operator,
            'is_admin' => $player->is_admin,
            'banned' => $player->banned,
            'country' => (string) data_get($player->meta, 'country', ''),
            'avatar_url' => (string) data_get($player->meta, 'avatar_url', ''),
            'last_seen_at' => optional($player->last_seen_at)->toAtomString(),
            'is_dummy' => false,
            'meta' => [
                ...($player->meta ?? []),
                'discord_user_id' => $player->discord_user_id,
                'provider' => 'discord_agent',
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function mergedMinecraftPlayers(Server $server, string $scope, ?string $search = null): array
    {
        $livePlayers = $this->minecraftProvider()?->list($server, $scope, $search) ?? [];
        $agentPlayers = $this->query($server, $scope, $search)
            ->orderByRaw("status = 'online' DESC")
            ->orderByDesc('last_seen_at')
            ->limit(250)
            ->get()
            ->map(fn (ServerPlayerSnapshot $player) => $this->transform($player))
            ->values()
            ->all();

        /** @var array<string, array<string, mixed>> $merged */
        $merged = [];

        foreach ($livePlayers as $player) {
            $merged[$this->playerMergeKey($player)] = $player;
        }

        foreach ($agentPlayers as $player) {
            $key = $this->playerMergeKey($player);
            $merged[$key] = isset($merged[$key])
                ? $this->mergePlayerRecords($merged[$key], $player)
                : $player;
        }

        $players = array_map(
            fn (array $player) => $this->withMinecraftGamemode($server, $player),
            array_values($merged)
        );
        usort($players, function (array $a, array $b): int {
            $aOnline = ($a['status'] ?? 'offline') === 'online' ? 0 : 1;
            $bOnline = ($b['status'] ?? 'offline') === 'online' ? 0 : 1;
            if ($aOnline !== $bOnline) {
                return $aOnline <=> $bOnline;
            }

            return strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
        });

        return $players;
    }

    /**
     * Prefer agent presence while keeping richer Minecraft metadata from the panel provider.
     *
     * @param array<string, mixed> $base
     * @param array<string, mixed> $agent
     * @return array<string, mixed>
     */
    private function mergePlayerRecords(array $base, array $agent): array
    {
        return [
            ...$base,
            ...$agent,
            'id' => (string) ($base['id'] ?? $agent['id'] ?? ''),
            'uuid' => $this->preferFilled($base['uuid'] ?? null, $agent['uuid'] ?? null),
            'source_id' => $this->preferFilled($base['source_id'] ?? null, $agent['source_id'] ?? null),
            'name' => $this->preferFilled($base['name'] ?? null, $agent['name'] ?? null),
            'status' => ($agent['status'] ?? 'offline') === 'online' ? 'online' : ($base['status'] ?? $agent['status'] ?? 'offline'),
            'ping' => max((int) ($base['ping'] ?? 0), (int) ($agent['ping'] ?? 0)),
            'role' => (string) ($agent['role'] ?? $base['role'] ?? 'player'),
            'is_operator' => (bool) (($base['is_operator'] ?? false) || ($agent['is_operator'] ?? false)),
            'is_admin' => (bool) (($base['is_admin'] ?? false) || ($agent['is_admin'] ?? false)),
            'banned' => (bool) (($base['banned'] ?? false) || ($agent['banned'] ?? false)),
            'country' => $this->preferFilled($agent['country'] ?? null, $base['country'] ?? null),
            'avatar_url' => $this->preferFilled($base['avatar_url'] ?? null, $agent['avatar_url'] ?? null),
            'last_seen_at' => $this->preferFilled($agent['last_seen_at'] ?? null, $base['last_seen_at'] ?? null),
            'is_dummy' => false,
            'meta' => [
                ...((array) ($base['meta'] ?? [])),
                ...((array) ($agent['meta'] ?? [])),
                'provider' => 'discord_agent',
                'minecraft_panel_provider' => true,
            ],
        ];
    }

    private function minecraftLiveProfile(Server $server, ServerPlayerSnapshot $player): ?array
    {
        $provider = $this->minecraftProvider();
        if (!$provider) {
            return null;
        }

        foreach (array_filter([$player->uuid, $player->name, $player->player_id]) as $candidate) {
            $profile = $provider->profile($server, (string) $candidate);
            if ($profile) {
                return $profile;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $profile
     * @return array<string, mixed>
     */
    private function withMinecraftGamemode(Server $server, array $profile): array
    {
        if (!$this->isMinecraftJava()) {
            return $profile;
        }

        $gamemode = $this->minecraftGamemode($server, (string) ($profile['id'] ?? $profile['uuid'] ?? $profile['name'] ?? ''));
        if ($gamemode === '-') {
            $gamemode = $this->minecraftGamemode($server, (string) ($profile['name'] ?? ''));
        }

        if ($gamemode === '-') {
            return $profile;
        }

        $profile['meta'] = [
            ...((array) ($profile['meta'] ?? [])),
            'current_gamemode' => $gamemode,
        ];

        return $profile;
    }

    private function minecraftGamemode(Server $server, string $playerId): string
    {
        if ($playerId === '') {
            return '-';
        }

        try {
            $statistics = $this->minecraftProvider()?->statistics($server, $playerId);
            foreach ((array) ($statistics['categories'] ?? []) as $category) {
                foreach ((array) ($category['entries'] ?? []) as $entry) {
                    if (mb_strtolower((string) ($entry['label'] ?? '')) === 'gamemode') {
                        $value = trim((string) ($entry['value'] ?? ''));

                        return $value !== '' ? $value : '-';
                    }
                }
            }
        } catch (Throwable) {
            return '-';
        }

        return '-';
    }

    /**
     * Keep agent-backed gamemode actions in sync with the live Minecraft provider cache.
     *
     * @param array<string, mixed> $profile
     */
    private function rememberMinecraftGamemode(Server $server, array $profile, string $mode): void
    {
        $label = $this->gamemodeLabelFromInput($mode);
        if ($label === '-') {
            return;
        }

        $identities = array_filter([
            $this->gamemodeCacheIdentity((string) ($profile['uuid'] ?? '')),
            $this->gamemodeCacheIdentity((string) ($profile['name'] ?? '')),
            $this->gamemodeCacheIdentity((string) ($profile['id'] ?? '')),
        ]);

        foreach (array_unique($identities) as $identity) {
            $cacheKey = $this->gamemodeCacheKey($server, $identity);
            Cache::put($cacheKey, $label, now()->addHours(6));
            Cache::put($this->gamemodeOverrideCacheKey($cacheKey), $label, now()->addMinutes(30));
        }

        $this->persistSnapshotGamemode($server, $profile, $label);
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

    private function gamemodeCacheKey(Server $server, string $identity): string
    {
        return sprintf('players:mcjava:gamemode:v1:%d:%s', $server->id, md5($identity));
    }

    private function gamemodeOverrideCacheKey(string $cacheKey): string
    {
        return $cacheKey . ':override';
    }

    private function gamemodeCacheIdentity(string $value): string
    {
        $normalizedUuid = $this->normalizeUuid($value);
        if ($normalizedUuid !== '') {
            return $normalizedUuid;
        }

        return mb_strtolower(trim($value));
    }

    /**
     * @param array<string, mixed> $profile
     */
    private function persistSnapshotGamemode(Server $server, array $profile, string $label): void
    {
        $query = $server->playerSnapshots()->where(function (Builder $query) use ($profile) {
            foreach (array_filter([
                (string) ($profile['id'] ?? ''),
                (string) ($profile['uuid'] ?? ''),
                (string) ($profile['name'] ?? ''),
            ]) as $identity) {
                $query
                    ->orWhere('player_id', $identity)
                    ->orWhere('uuid', $identity)
                    ->orWhere('name', $identity);
            }
        });

        $snapshot = $query->first();
        if (!$snapshot) {
            return;
        }

        $meta = (array) ($snapshot->meta ?? []);
        $meta['current_gamemode'] = $label;
        $snapshot->forceFill(['meta' => $meta])->save();
    }

    private function normalizeUuid(string $value): string
    {
        $value = strtolower(trim($value));
        if ($value === '') {
            return '';
        }

        $value = str_replace('-', '', $value);

        return preg_match('/^[a-f0-9]{32}$/', $value) ? $value : '';
    }

    private function pingLabel(mixed $value): string
    {
        $ping = (int) ($value ?? 0);

        return $ping > 0 ? $ping . 'ms' : '-';
    }

    /**
     * @param array<string, mixed> $player
     */
    private function playerMergeKey(array $player): string
    {
        $name = trim(mb_strtolower((string) ($player['name'] ?? '')));
        if ($name !== '') {
            return 'name:' . $name;
        }

        $uuid = trim(mb_strtolower((string) ($player['uuid'] ?? '')));
        if ($uuid !== '' && !str_contains($uuid, 'online-player')) {
            return 'uuid:' . $uuid;
        }

        return 'id:' . trim(mb_strtolower((string) ($player['id'] ?? 'player')));
    }

    private function preferFilled(mixed $first, mixed $second): string
    {
        $first = trim((string) ($first ?? ''));
        if ($first !== '') {
            return $first;
        }

        return trim((string) ($second ?? ''));
    }

    private function actionGroups(array $capabilities): array
    {
        if (!($capabilities['console_bridge'] ?? false)) {
            return [];
        }

        if ($this->isMinecraftJava()) {
            return $this->minecraftActionGroups();
        }

        return [
            [
                'id' => 'agent',
                'title' => 'Agent Actions',
                'description' => 'Actions are sent through the Discord Agent command bridge.',
                'actions' => [
                    [
                        'id' => 'message',
                        'label' => 'Message',
                        'description' => 'Send a direct in-game message to this player.',
                        'tone' => 'primary',
                        'requires_input' => true,
                        'input_key' => 'message',
                        'input_label' => 'Message',
                        'input_placeholder' => 'Type the message to send...',
                    ],
                    [
                        'id' => 'kick',
                        'label' => 'Kick',
                        'description' => 'Kick this player from the server.',
                        'tone' => 'warning',
                        'requires_input' => true,
                        'input_key' => 'reason',
                        'input_label' => 'Reason',
                        'input_placeholder' => 'Reason shown to the player...',
                    ],
                    [
                        'id' => 'ban',
                        'label' => 'Ban',
                        'description' => 'Ban this player using the game adapter.',
                        'tone' => 'danger',
                        'requires_input' => true,
                        'input_key' => 'reason',
                        'input_label' => 'Reason',
                        'input_placeholder' => 'Reason stored by the server...',
                    ],
                ],
            ],
        ];
    }

    private function actionGroupsForProfile(ServerPlayerSnapshot $player, array $capabilities): array
    {
        return $this->actionGroupsForProfileData($this->transform($player), $capabilities);
    }

    /**
     * @param array<string, mixed> $player
     * @return array<int, array<string, mixed>>
     */
    private function actionGroupsForProfileData(array $player, array $capabilities): array
    {
        $groups = $this->actionGroups($capabilities);
        if (!$this->isMinecraftJava() || !(bool) ($player['banned'] ?? false)) {
            return $groups;
        }

        foreach ($groups as &$group) {
            foreach ($group['actions'] as &$action) {
                if (($action['id'] ?? '') !== 'ban') {
                    continue;
                }

                $action = [
                    'id' => 'unban',
                    'label' => 'Unban',
                    'description' => 'Remove ban and allow this player to reconnect.',
                    'tone' => 'success',
                    'command' => 'pardon {{player}}',
                ];
            }
            unset($action);
        }
        unset($group);

        return $groups;
    }

    private function minecraftActionGroups(): array
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
     * @param array<string, mixed> $player
     * @return array<string, array<string, mixed>>
     */
    private function actionMap(array $player, array $capabilities): array
    {
        $map = [];
        foreach ($this->actionGroupsForProfileData($player, ['console_bridge' => true, ...$capabilities]) as $group) {
            foreach ((array) ($group['actions'] ?? []) as $action) {
                $map[(string) ($action['id'] ?? '')] = $action;
            }
        }

        return $map;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveActionPlayer(Server $server, string $playerId): ?array
    {
        $snapshot = $this->resolveSnapshot($server, $playerId);
        if ($snapshot) {
            $live = $this->isMinecraftJava() ? $this->minecraftLiveProfile($server, $snapshot) : null;

            return $live ? $this->mergePlayerRecords($live, $this->transform($snapshot)) : $this->transform($snapshot);
        }

        if ($this->isMinecraftJava()) {
            return $this->minecraftProvider()?->profile($server, $playerId);
        }

        return null;
    }

    private function buildCommand(array $action, array $player, array $context): string
    {
        $replacements = [
            '{{player}}' => (string) ($player['name'] ?? ''),
            '{{uuid}}' => (string) ($player['uuid'] ?? $player['id'] ?? ''),
            '{{id}}' => (string) ($player['id'] ?? ''),
            '{{text}}' => (string) ($context['text'] ?? $context['message'] ?? ''),
            '{{reason}}' => (string) ($context['reason'] ?? ''),
        ];

        foreach ($context as $key => $value) {
            if (!is_string($key) || $key === '') {
                continue;
            }

            $replacements['{{' . $key . '}}'] = (string) (is_scalar($value) ? $value : '');
        }

        $command = str_replace(array_keys($replacements), array_values($replacements), (string) ($action['command'] ?? ''));

        return trim(preg_replace('/\s+/', ' ', $command) ?? $command);
    }

    private function resolveSnapshot(Server $server, string $playerId): ?ServerPlayerSnapshot
    {
        return $server->playerSnapshots()
            ->where(fn (Builder $query) => $query
                ->where('player_id', $playerId)
                ->orWhere('uuid', $playerId)
                ->orWhere('name', $playerId)
            )
            ->first();
    }

    private function delegateMinecraftInventory(Server $server, string $playerId): ?array
    {
        try {
            $live = $this->minecraftProvider();
            if (!$live) {
                return null;
            }

            $player = $this->resolveSnapshot($server, $playerId);
            foreach (array_filter([$player?->uuid, $player?->name, $playerId]) as $candidate) {
                $result = $live->inventory($server, (string) $candidate);
                if (($result['available'] ?? false) || ($result['message'] ?? '') !== 'Player profile is unavailable.') {
                    return $result;
                }
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    private function delegateMinecraftStatistics(Server $server, string $playerId): ?array
    {
        try {
            $live = $this->minecraftProvider();
            if (!$live) {
                return null;
            }

            $player = $this->resolveSnapshot($server, $playerId);
            foreach (array_filter([$player?->uuid, $player?->name, $playerId]) as $candidate) {
                $result = $live->statistics($server, (string) $candidate);
                if (($result['available'] ?? false) || ($result['message'] ?? '') !== 'Player profile is unavailable.') {
                    return $result;
                }
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    private function minecraftProvider(): ?MinecraftJavaLivePlayerProvider
    {
        return $this->isMinecraftJava() ? app(MinecraftJavaLivePlayerProvider::class) : null;
    }

    private function snapshotStatistics(ServerPlayerSnapshot $player): array
    {
        return [
            [
                'id' => 'presence',
                'title' => 'Presence',
                'entries' => [
                    ['label' => 'Status', 'value' => strtoupper((string) $player->status)],
                    ['label' => 'Ping', 'value' => $this->pingLabel($player->ping)],
                    ['label' => 'Operator', 'value' => $player->is_operator ? 'Yes' : 'No'],
                    ['label' => 'Banned', 'value' => $player->banned ? 'Yes' : 'No'],
                ],
            ],
            [
                'id' => 'identity',
                'title' => 'Identity',
                'entries' => [
                    ['label' => 'Name', 'value' => $player->name],
                    ['label' => 'UUID', 'value' => $player->uuid ?: $player->player_id],
                    ['label' => 'Discord', 'value' => $player->discord_user_id ? 'Linked' : '-'],
                ],
            ],
        ];
    }

    private function isMinecraftJava(): bool
    {
        return $this->type === GameType::MINECRAFT_JAVA;
    }
}
