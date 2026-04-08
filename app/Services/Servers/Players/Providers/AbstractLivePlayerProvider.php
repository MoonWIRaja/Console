<?php

namespace Pterodactyl\Services\Servers\Players\Providers;

use Illuminate\Support\Str;
use Pterodactyl\Models\Server;
use Psr\Http\Message\ResponseInterface;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Services\Servers\Players\PlayerScope;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;
use Pterodactyl\Services\Servers\Players\Contracts\PlayerProviderInterface;
use Pterodactyl\Repositories\Wings\DaemonCommandRepository;

abstract class AbstractLivePlayerProvider implements PlayerProviderInterface
{
    public function __construct(
        protected DaemonFileRepository $fileRepository,
        protected DaemonCommandRepository $commandRepository,
    ) {
    }

    public function capabilities(Server $server): array
    {
        return [
            'filters' => [
                [
                    'id' => PlayerScope::ALL,
                    'label' => 'All Players',
                    'description' => 'All player records currently known to this provider.',
                ],
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
            'action_groups' => [],
            'tabs' => ['overview', 'inventory', 'statistics'],
            'notes' => [],
            'integrations' => [],
            'supports_live_data' => true,
            'supports_player_list' => true,
            'supports_counts' => true,
            'supported_scopes' => PlayerScope::all(),
        ];
    }

    public function counts(Server $server): array
    {
        $players = $this->players($server);

        return [
            'total' => $this->fetchPlayerCountFromServer($server) ?: count($players),
            'online' => count(array_filter($players, fn (array $player) => ($player['status'] ?? 'offline') === 'online')),
            'operators' => count(array_filter($players, fn (array $player) => $this->isOperator($player))),
            'admins' => count(array_filter($players, fn (array $player) => $this->isAdmin($player))),
            'staff' => count(array_filter($players, fn (array $player) => $this->isStaff($player))),
            'banned' => count(array_filter($players, fn (array $player) => (bool) ($player['banned'] ?? false))),
            'max' => $this->fetchMaxPlayersFromServer($server),
        ];
    }

    public function list(Server $server, string $scope, ?string $search = null): array
    {
        return array_values($this->filteredPlayers($this->players($server), $scope, $search));
    }

    public function profile(Server $server, string $playerId): ?array
    {
        $player = $this->findPlayerById($server, $playerId);
        if (!$player) {
            return null;
        }

        return [
            ...$player,
            'is_dummy' => false,
        ];
    }

    public function inventory(Server $server, string $playerId): array
    {
        return [
            'available' => false,
            'message' => 'Inventory API is not available for this game type yet.',
            'sections' => [],
            'summary' => [],
            'is_dummy' => false,
            'player_id' => $playerId,
        ];
    }

    public function statistics(Server $server, string $playerId): array
    {
        return [
            'available' => false,
            'message' => 'Statistics API is not available for this game type yet.',
            'categories' => [],
            'is_dummy' => false,
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
                'context' => $context,
                'is_dummy' => false,
            ];
        }

        $result = $this->performActionOnServer($server, $playerId, $action, $context);
        $accepted = (bool) ($result['success'] ?? false);

        return [
            'accepted' => $accepted,
            'queued' => false,
            'message' => (string) ($result['message'] ?? ($accepted ? 'Action executed.' : 'Action failed.')),
            'action' => (string) ($result['action'] ?? $action),
            'action_label' => (string) ($result['action_label'] ?? $action),
            'player_id' => $playerId,
            'context' => $context,
            'command_preview' => $result['command_preview'] ?? null,
            'is_dummy' => false,
        ];
    }

    abstract protected function fetchPlayersFromServer(Server $server): array;

    abstract protected function fetchPlayerCountFromServer(Server $server): int;

    abstract protected function fetchMaxPlayersFromServer(Server $server): int;

    abstract protected function performActionOnServer(
        Server $server,
        string $playerId,
        string $actionId,
        array $context = []
    ): array;

    protected function players(Server $server): array
    {
        return $this->normalizePlayers($this->fetchPlayersFromServer($server));
    }

    protected function findPlayerById(Server $server, string $playerId): ?array
    {
        foreach ($this->players($server) as $player) {
            if ((string) ($player['id'] ?? '') === (string) $playerId) {
                return $player;
            }
        }

        return null;
    }

    protected function sendRconCommand(Server $server, string $command): string
    {
        try {
            $response = $this->commandRepository->setServer($server)->send($command);
            return $this->extractDaemonResponseBody($response);
        } catch (\Throwable $exception) {
            Log::warning('Live player RCON command failed.', [
                'server_id' => $server->id,
                'command' => $command,
                'provider' => static::class,
                'error' => $exception->getMessage(),
            ]);

            return '';
        }
    }

    /**
     * @param array<int, array<string, mixed>> $players
     *
     * @return array<int, array<string, mixed>>
     */
    protected function normalizePlayers(array $players): array
    {
        $normalized = [];

        foreach ($players as $index => $player) {
            $name = trim((string) ($player['name'] ?? ('Player ' . ($index + 1))));
            if ($name === '') {
                continue;
            }

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
                'meta' => is_array($player['meta'] ?? null) ? $player['meta'] : [],
                'is_dummy' => false,
            ];
        }

        return $normalized;
    }

    /**
     * @param array<int, array<string, mixed>> $players
     *
     * @return array<int, array<string, mixed>>
     */
    protected function filteredPlayers(array $players, string $scope, ?string $search = null): array
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

    protected function fallbackAvatar(string $seed): string
    {
        return 'https://api.dicebear.com/9.x/initials/svg?seed=' . rawurlencode($seed);
    }

    protected function isOperator(array $player): bool
    {
        return (bool) ($player['is_operator'] ?? false) || in_array((string) ($player['role'] ?? ''), ['operator', 'admin'], true);
    }

    protected function isAdmin(array $player): bool
    {
        return (bool) ($player['is_admin'] ?? false) || (string) ($player['role'] ?? '') === 'admin';
    }

    protected function isStaff(array $player): bool
    {
        $role = (string) ($player['role'] ?? '');

        return in_array($role, ['operator', 'admin', 'moderator'], true)
            || $this->isOperator($player)
            || $this->isAdmin($player);
    }

    private function extractDaemonResponseBody(ResponseInterface $response): string
    {
        $body = trim((string) $response->getBody());
        if ($body === '') {
            return '';
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            return $body;
        }

        foreach (['output', 'response', 'message', 'data'] as $key) {
            $value = $decoded[$key] ?? null;
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return $body;
    }
}
