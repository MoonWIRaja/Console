<?php

namespace Pterodactyl\Services\Servers\Players\Providers;

use Throwable;
use Illuminate\Support\Str;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\ServerVariable;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Services\Servers\Players\PlayerScope;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;
use Pterodactyl\Services\Servers\Players\Contracts\PlayerProviderInterface;
use Pterodactyl\Repositories\Wings\DaemonCommandRepository;
use Pterodactyl\Services\Servers\Players\Support\SourceRconClient;

abstract class AbstractLivePlayerProvider implements PlayerProviderInterface
{
    /**
     * One connection per server id, reused across the several calls a single
     * request can make (counts(), list(), profile()...) instead of a fresh
     * TCP handshake + auth for each.
     *
     * @var array<int, SourceRconClient|false>
     */
    private array $rconClients = [];

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

    /**
     * Resolves this server's real RCON host/port/password, or null if RCON isn't
     * configured/enabled for it (e.g. no port variable set, or explicitly
     * disabled) - sendRconCommand() then fails closed with an empty response
     * rather than attempting a connection that can't succeed.
     *
     * @return array{host: string, port: int, password: string}|null
     */
    abstract protected function resolveRconCredentials(Server $server): ?array;

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

    /**
     * Runs a command over a real RCON socket and returns its output.
     *
     * This used to go through Wings' "send a console command" API instead
     * (DaemonCommandRepository::send()) - confirmed empirically against a real
     * server that this always replies 204 No Content with an empty body, since
     * it's fire-and-forget by design (console output only exists on the
     * websocket stream, not as this call's response). Every provider reading
     * that response was therefore always working from an empty string. Talking
     * to the game's actual RCON port directly is the only way to get real output
     * back.
     */
    protected function sendRconCommand(Server $server, string $command): string
    {
        $client = $this->rconClient($server);
        if ($client === null) {
            return '';
        }

        try {
            return $client->execute($command);
        } catch (Throwable $exception) {
            Log::warning('Live player RCON command failed.', [
                'server_id' => $server->id,
                'command' => $command,
                'provider' => static::class,
                'error' => $exception->getMessage(),
            ]);

            unset($this->rconClients[$server->id]);

            return '';
        }
    }

    /**
     * Shared helper for the common case: RCON port/password come from an egg's
     * server_variables, and the host is just the server's allocated IP (RCON
     * listens on the same public interface as the game, on its own port).
     * Individual providers call this with whichever variable names their egg
     * actually uses, since there's no universal convention across eggs.
     *
     * @param string[] $portVariables Tried in order; first non-empty value wins.
     * @param string[] $passwordVariables Tried in order; first non-empty value wins.
     * @return array{host: string, port: int, password: string}|null
     */
    protected function resolveRconFromVariables(
        Server $server,
        array $portVariables,
        array $passwordVariables,
        ?int $defaultPort = null,
    ): ?array {
        $server->loadMissing('allocation');
        if (!$server->allocation) {
            return null;
        }

        $values = ServerVariable::query()
            ->where('server_id', $server->id)
            ->with('variable')
            ->get()
            ->filter(fn (ServerVariable $v) => $v->variable !== null)
            ->keyBy(fn (ServerVariable $v) => $v->variable->env_variable);

        $findFirst = function (array $names) use ($values): ?string {
            foreach ($names as $name) {
                $value = trim((string) ($values->get($name)?->variable_value ?? ''));
                if ($value !== '') {
                    return $value;
                }
            }

            return null;
        };

        $port = $findFirst($portVariables);
        $port = $port !== null ? (int) $port : $defaultPort;
        if (!$port) {
            return null;
        }

        $password = $findFirst($passwordVariables) ?? '';

        return [
            'host' => $server->allocation->ip,
            'port' => $port,
            'password' => $password,
        ];
    }

    private function rconClient(Server $server): ?SourceRconClient
    {
        if (array_key_exists($server->id, $this->rconClients)) {
            return $this->rconClients[$server->id] ?: null;
        }

        $credentials = $this->resolveRconCredentials($server);
        if ($credentials === null) {
            $this->rconClients[$server->id] = false;

            return null;
        }

        try {
            $client = new SourceRconClient($credentials['host'], $credentials['port'], $credentials['password']);
        } catch (Throwable $exception) {
            Log::warning('Failed to connect to live player RCON.', [
                'server_id' => $server->id,
                'host' => $credentials['host'],
                'port' => $credentials['port'],
                'provider' => static::class,
                'error' => $exception->getMessage(),
            ]);

            $this->rconClients[$server->id] = false;

            return null;
        }

        $this->rconClients[$server->id] = $client;

        return $client;
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

}
