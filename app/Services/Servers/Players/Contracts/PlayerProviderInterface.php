<?php

namespace Pterodactyl\Services\Servers\Players\Contracts;

use Pterodactyl\Models\Server;

interface PlayerProviderInterface
{
    public function gameType(): string;

    public function gameLabel(): string;

    public function capabilities(Server $server): array;

    public function counts(Server $server): array;

    public function list(Server $server, string $scope, ?string $search = null): array;

    public function profile(Server $server, string $playerId): ?array;

    public function inventory(Server $server, string $playerId): array;

    public function statistics(Server $server, string $playerId): array;

    public function performAction(Server $server, string $playerId, string $action, array $context = []): array;
}
