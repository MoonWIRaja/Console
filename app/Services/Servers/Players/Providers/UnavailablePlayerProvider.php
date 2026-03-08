<?php

namespace Pterodactyl\Services\Servers\Players\Providers;

use Pterodactyl\Models\Server;
use Pterodactyl\Services\Servers\Players\GameType;
use Pterodactyl\Services\Servers\Players\PlayerScope;
use Pterodactyl\Services\Servers\Players\Contracts\PlayerProviderInterface;

class UnavailablePlayerProvider implements PlayerProviderInterface
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
        return GameType::label($this->type);
    }

    public function capabilities(Server $server): array
    {
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
            'action_groups' => [],
            'tabs' => ['overview'],
            'notes' => [
                'Live player integration is not configured for this server yet.',
            ],
            'integrations' => [],
        ];
    }

    public function counts(Server $server): array
    {
        return [
            'total' => 0,
            'online' => 0,
            'operators' => 0,
            'admins' => 0,
            'staff' => 0,
            'banned' => 0,
        ];
    }

    public function list(Server $server, string $scope, ?string $search = null): array
    {
        return [];
    }

    public function profile(Server $server, string $playerId): ?array
    {
        return null;
    }

    public function inventory(Server $server, string $playerId): array
    {
        return [
            'available' => false,
            'message' => 'Live inventory bridge is not configured.',
            'sections' => [],
            'summary' => [],
            'player_id' => $playerId,
        ];
    }

    public function statistics(Server $server, string $playerId): array
    {
        return [
            'available' => false,
            'message' => 'Live statistics bridge is not configured.',
            'categories' => [],
            'player_id' => $playerId,
        ];
    }

    public function performAction(Server $server, string $playerId, string $action, array $context = []): array
    {
        return [
            'accepted' => false,
            'queued' => false,
            'message' => 'Player action is unavailable until live integration is configured.',
            'action' => $action,
            'player_id' => $playerId,
            'context' => $context,
        ];
    }
}

