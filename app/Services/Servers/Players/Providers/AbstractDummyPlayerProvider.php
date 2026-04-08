<?php

namespace Pterodactyl\Services\Servers\Players\Providers;

use Illuminate\Support\Str;
use Pterodactyl\Models\Server;
use Pterodactyl\Services\Servers\Players\PlayerScope;
use Pterodactyl\Services\Servers\Players\Contracts\PlayerProviderInterface;

abstract class AbstractDummyPlayerProvider implements PlayerProviderInterface
{
    public function capabilities(Server $server): array
    {
        return [
            'filters' => $this->filters(),
            'action_groups' => $this->actionGroups($server),
            'tabs' => $this->tabs($server),
            'notes' => $this->notes($server),
            'integrations' => $this->integrations($server),
        ];
    }

    public function counts(Server $server): array
    {
        $players = $this->players($server);

        return [
            'total' => count($players),
            'online' => count(array_filter($players, fn (array $player) => ($player['status'] ?? 'offline') === 'online')),
            'operators' => count(array_filter($players, fn (array $player) => $this->isOperator($player))),
            'admins' => count(array_filter($players, fn (array $player) => $this->isAdmin($player))),
            'staff' => count(array_filter($players, fn (array $player) => $this->isStaff($player))),
            'banned' => count(array_filter($players, fn (array $player) => (bool) ($player['banned'] ?? false))),
        ];
    }

    public function list(Server $server, string $scope, ?string $search = null): array
    {
        return array_values($this->filteredPlayers($this->players($server), $scope, $search));
    }

    public function profile(Server $server, string $playerId): ?array
    {
        foreach ($this->players($server) as $player) {
            if ((string) $player['id'] !== (string) $playerId) {
                continue;
            }

            return [
                ...$player,
                'is_dummy' => true,
                'action_groups' => $this->actionGroups($server),
            ];
        }

        return null;
    }

    public function inventory(Server $server, string $playerId): array
    {
        return [
            'available' => false,
            'message' => 'Inventory API is not available for this game type yet.',
            'sections' => [],
            'summary' => [],
            'is_dummy' => true,
            'player_id' => $playerId,
        ];
    }

    public function statistics(Server $server, string $playerId): array
    {
        return [
            'available' => false,
            'message' => 'Statistics API is not available for this game type yet.',
            'categories' => [],
            'is_dummy' => true,
            'player_id' => $playerId,
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
                'is_dummy' => true,
            ];
        }

        $map = [];
        foreach ($this->actionGroups($server) as $group) {
            foreach ($group['actions'] ?? [] as $item) {
                $map[(string) ($item['id'] ?? '')] = $item;
            }
        }

        $selected = $map[$action] ?? null;
        if (!$selected) {
            return [
                'accepted' => false,
                'queued' => false,
                'message' => 'Action is not supported for this game type.',
                'action' => $action,
                'player_id' => $playerId,
                'is_dummy' => true,
            ];
        }

        $replacements = [
            '{{player}}' => (string) ($profile['name'] ?? ''),
            '{{uuid}}' => (string) ($profile['uuid'] ?? ''),
            '{{id}}' => (string) ($profile['id'] ?? ''),
        ];

        foreach ($context as $key => $value) {
            if (is_scalar($value) || is_null($value)) {
                $replacements['{{' . $key . '}}'] = (string) $value;
            }
        }

        $command = (string) ($selected['command'] ?? '');
        if ($command !== '') {
            $command = str_replace(array_keys($replacements), array_values($replacements), $command);
        }

        return [
            'accepted' => true,
            'queued' => true,
            'message' => 'Dummy action accepted. Live command bridge is not enabled yet.',
            'action' => $action,
            'action_label' => (string) ($selected['label'] ?? $action),
            'player_id' => $playerId,
            'command_preview' => $command,
            'context' => $context,
            'is_dummy' => true,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function players(Server $server): array
    {
        return $this->normalizePlayers($this->rawPlayers($server));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    abstract protected function rawPlayers(Server $server): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function actionGroups(Server $server): array
    {
        return [
            [
                'id' => 'general',
                'title' => 'General Actions',
                'description' => 'Base moderation and communication controls.',
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
                        'description' => 'Teleport player to a safe location.',
                        'tone' => 'neutral',
                        'command' => 'tp {{player}} 0 80 0',
                    ],
                    [
                        'id' => 'kick',
                        'label' => 'Kick',
                        'description' => 'Remove player from the server session.',
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
                        'description' => 'Ban player account from joining.',
                        'tone' => 'danger',
                        'command' => 'ban {{player}} {{reason}}',
                        'requires_input' => true,
                        'input_key' => 'reason',
                        'input_label' => 'Reason',
                        'input_placeholder' => 'Reason for ban',
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    protected function filters(): array
    {
        return [
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
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function tabs(Server $server): array
    {
        return ['overview', 'inventory', 'statistics'];
    }

    /**
     * @return array<int, string>
     */
    protected function notes(Server $server): array
    {
        return ['Dummy backend is active. Replace provider bridge with live RCON/query API when ready.'];
    }

    protected function integrations(Server $server): array
    {
        return [];
    }

    /**
     * @param array<int, array<string, mixed>> $players
     *
     * @return array<int, array<string, mixed>>
     */
    private function normalizePlayers(array $players): array
    {
        $normalized = [];
        foreach ($players as $index => $player) {
            $name = (string) ($player['name'] ?? ('Player ' . ($index + 1)));
            $id = (string) ($player['id'] ?? Str::slug($name));
            $role = (string) ($player['role'] ?? 'player');

            $normalized[] = [
                'id' => $id,
                'name' => $name,
                'uuid' => (string) ($player['uuid'] ?? ''),
                'source_id' => (string) ($player['source_id'] ?? $id),
                'status' => (string) ($player['status'] ?? 'online'),
                'ping' => (int) ($player['ping'] ?? 0),
                'role' => $role,
                'is_operator' => (bool) ($player['is_operator'] ?? in_array($role, ['operator', 'admin'], true)),
                'is_admin' => (bool) ($player['is_admin'] ?? $role === 'admin'),
                'banned' => (bool) ($player['banned'] ?? false),
                'country' => (string) ($player['country'] ?? ''),
                'avatar_url' => (string) ($player['avatar_url'] ?? $this->fallbackAvatar($name)),
                'last_seen_at' => (string) ($player['last_seen_at'] ?? ''),
                'is_dummy' => true,
            ];
        }

        return $normalized;
    }

    /**
     * @param array<int, array<string, mixed>> $players
     *
     * @return array<int, array<string, mixed>>
     */
    private function filteredPlayers(array $players, string $scope, ?string $search = null): array
    {
        $filtered = array_filter($players, function (array $player) use ($scope) {
            return match ($scope) {
                PlayerScope::ONLINE => ($player['status'] ?? 'offline') === 'online',
                PlayerScope::OPERATORS => $this->isOperator($player),
                PlayerScope::ADMINS => $this->isAdmin($player),
                PlayerScope::STAFF => $this->isStaff($player),
                PlayerScope::BANNED => (bool) ($player['banned'] ?? false),
                default => true,
            };
        });

        $needle = trim((string) $search);
        if ($needle === '') {
            return $filtered;
        }

        $needle = mb_strtolower($needle);

        return array_filter($filtered, function (array $player) use ($needle) {
            $haystack = mb_strtolower(implode(' ', [
                (string) ($player['name'] ?? ''),
                (string) ($player['id'] ?? ''),
                (string) ($player['uuid'] ?? ''),
                (string) ($player['source_id'] ?? ''),
            ]));

            return Str::contains($haystack, $needle);
        });
    }

    private function fallbackAvatar(string $seed): string
    {
        return 'https://api.dicebear.com/9.x/initials/svg?seed=' . rawurlencode($seed);
    }

    private function isOperator(array $player): bool
    {
        return (bool) ($player['is_operator'] ?? false) || in_array((string) ($player['role'] ?? ''), ['operator', 'admin'], true);
    }

    private function isAdmin(array $player): bool
    {
        return (bool) ($player['is_admin'] ?? false) || (string) ($player['role'] ?? '') === 'admin';
    }

    private function isStaff(array $player): bool
    {
        $role = (string) ($player['role'] ?? '');

        return in_array($role, ['operator', 'admin', 'moderator'], true)
            || $this->isOperator($player)
            || $this->isAdmin($player);
    }
}
