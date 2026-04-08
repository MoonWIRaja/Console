<?php

namespace Pterodactyl\Services\Servers\Players;

use Pterodactyl\Models\Server;
use Pterodactyl\Services\Servers\Players\Support\PlayerGameTypeResolver;
use Pterodactyl\Services\Servers\Players\Contracts\PlayerProviderInterface;

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
            'capabilities' => $provider->capabilities($server),
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
            'capabilities' => $provider->capabilities($server),
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
}
