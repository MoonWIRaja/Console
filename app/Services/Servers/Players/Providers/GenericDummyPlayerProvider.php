<?php

namespace Pterodactyl\Services\Servers\Players\Providers;

use Pterodactyl\Models\Server;
use Pterodactyl\Services\Servers\Players\GameType;

class GenericDummyPlayerProvider extends AbstractDummyPlayerProvider
{
    public function gameType(): string
    {
        return GameType::GENERIC;
    }

    public function gameLabel(): string
    {
        return GameType::label(GameType::GENERIC);
    }

    protected function rawPlayers(Server $server): array
    {
        return [
            [
                'id' => 'generic:alpha',
                'name' => 'AlphaUser',
                'uuid' => 'generic-alpha-user',
                'source_id' => 'generic-alpha-user',
                'status' => 'online',
                'ping' => 44,
                'role' => 'admin',
                'country' => 'MY',
                'avatar_url' => 'https://api.dicebear.com/9.x/initials/svg?seed=AlphaUser',
                'last_seen_at' => now()->subMinutes(3)->toIso8601String(),
            ],
            [
                'id' => 'generic:beta',
                'name' => 'BetaUser',
                'uuid' => 'generic-beta-user',
                'source_id' => 'generic-beta-user',
                'status' => 'offline',
                'ping' => 0,
                'role' => 'player',
                'country' => 'SG',
                'avatar_url' => 'https://api.dicebear.com/9.x/initials/svg?seed=BetaUser',
                'last_seen_at' => now()->subHours(8)->toIso8601String(),
            ],
        ];
    }

    protected function notes(Server $server): array
    {
        return [
            'No game-specific provider matched this server.',
            'Generic dummy provider is active until a game bridge is configured.',
        ];
    }

    protected function tabs(Server $server): array
    {
        return ['overview'];
    }
}
