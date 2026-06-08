<?php

namespace Pterodactyl\Services\Servers\Players;

use Pterodactyl\Models\Server;
use Pterodactyl\Services\Servers\Players\Support\PlayerGameTypeResolver;
use Pterodactyl\Services\Servers\Players\Contracts\PlayerProviderInterface;
use Pterodactyl\Services\Servers\Players\Providers\AgentPlayerSnapshotProvider;

class PlayerDirectoryService
{
    public function __construct(
        private PlayerGameTypeResolver $resolver,
        private PlayerProviderRegistry $registry,
    ) {
    }

    public function list(Server $server, ?string $scope = null, ?string $search = null): array
    {
        $provider = $this->provider($server);
        $resolvedScope = $this->sanitizeScope($scope);

        return [
            'game' => $this->gameMeta($provider),
            'scope' => $resolvedScope,
            'search' => trim((string) $search),
            'counts' => $provider->counts($server),
            'capabilities' => $this->withDiscordAgentState($server, $provider->capabilities($server)),
            'items' => $provider->list($server, $resolvedScope, $search),
            'is_dummy' => false,
        ];
    }

    public function capabilities(Server $server): array
    {
        $provider = $this->provider($server);

        return [
            'game' => $this->gameMeta($provider),
            'counts' => $provider->counts($server),
            'capabilities' => $this->withDiscordAgentState($server, $provider->capabilities($server)),
            'is_dummy' => false,
        ];
    }

    public function profile(Server $server, string $playerId): ?array
    {
        $provider = $this->provider($server);
        $profile = $provider->profile($server, $playerId);

        if (!$profile) {
            return null;
        }

        return [
            'game' => $this->gameMeta($provider),
            'player' => $profile,
            'is_dummy' => false,
        ];
    }

    public function inventory(Server $server, string $playerId): ?array
    {
        $provider = $this->provider($server);
        if (!$provider->profile($server, $playerId)) {
            return null;
        }

        return [
            'game' => $this->gameMeta($provider),
            ...$provider->inventory($server, $playerId),
            'is_dummy' => false,
        ];
    }

    public function statistics(Server $server, string $playerId): ?array
    {
        $provider = $this->provider($server);
        if (!$provider->profile($server, $playerId)) {
            return null;
        }

        return [
            'game' => $this->gameMeta($provider),
            ...$provider->statistics($server, $playerId),
            'is_dummy' => false,
        ];
    }

    public function action(Server $server, string $playerId, string $action, array $context = []): ?array
    {
        $provider = $this->provider($server);
        if (!$provider->profile($server, $playerId)) {
            return null;
        }

        return [
            'game' => $this->gameMeta($provider),
            ...$provider->performAction($server, $playerId, $action, $context),
            'is_dummy' => false,
        ];
    }

    private function provider(Server $server): PlayerProviderInterface
    {
        $server->loadMissing(['discordAgent']);
        $agent = $server->discordAgent;
        if (
            $agent
            && $agent->connection_status === 'connected'
            && $agent->last_seen_at
            && $agent->last_seen_at->gt(now()->subSeconds(90))
        ) {
            return new AgentPlayerSnapshotProvider($agent->detected_game_type ?: $this->resolver->resolve($server));
        }

        return $this->registry->forType($this->resolver->resolve($server));
    }

    private function sanitizeScope(?string $scope): string
    {
        if ($scope && in_array($scope, PlayerScope::all(), true)) {
            return $scope;
        }

        return PlayerScope::ONLINE;
    }

    private function gameMeta(PlayerProviderInterface $provider): array
    {
        return [
            'type' => $provider->gameType(),
            'label' => $provider->gameLabel(),
            'is_dummy' => false,
        ];
    }

    private function withDiscordAgentState(Server $server, array $capabilities): array
    {
        $server->loadMissing(['discordIntegration', 'discordAgent']);
        $integration = $server->discordIntegration;
        $agent = $server->discordAgent;
        $connected = $agent
            && $agent->connection_status === 'connected'
            && $agent->last_seen_at
            && $agent->last_seen_at->gt(now()->subSeconds(90));

        $capabilities['integrations'] = [
            ...($capabilities['integrations'] ?? []),
            'discord_agent' => [
                'enabled' => (bool) ($integration?->enabled),
                'installed' => $agent && $agent->install_status !== 'not_installed',
                'install_status' => $agent?->install_status ?? 'not_installed',
                'connection_status' => $connected ? 'connected' : ($agent?->connection_status ?? 'offline'),
                'adapter' => $agent?->adapter,
                'detected_game_type' => $agent?->detected_game_type,
                'detection_confidence' => $agent?->detection_confidence ?? 0,
                'last_seen_at' => optional($agent?->last_seen_at)->toAtomString(),
                'source_label' => $connected ? 'Synced by Discord Agent' : 'Panel player provider fallback',
            ],
        ];

        $capabilities['state'] = [
            ...($capabilities['state'] ?? []),
            'player_source' => $connected ? 'agent' : 'panel_fallback',
            'player_source_label' => $connected ? 'Synced by Discord Agent' : 'Using panel fallback',
        ];

        return $capabilities;
    }
}
