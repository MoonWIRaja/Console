<?php

namespace Pterodactyl\Services\Servers\Players\Providers;

use Pterodactyl\Models\Server;
use Pterodactyl\Services\Servers\Players\GameType;

class HytaleDummyPlayerProvider extends AbstractDummyPlayerProvider
{
    public function gameType(): string
    {
        return GameType::HYTALE;
    }

    public function gameLabel(): string
    {
        return GameType::label(GameType::HYTALE);
    }

    protected function rawPlayers(Server $server): array
    {
        return [
            [
                'id' => 'hytale:owls',
                'name' => 'OwlsCrafter',
                'uuid' => '52ccf93b-9231-4564-aad7-64deb7dd739a',
                'source_id' => 'hytale:52ccf93b-9231-4564-aad7-64deb7dd739a',
                'status' => 'online',
                'ping' => 35,
                'role' => 'admin',
                'country' => 'MY',
                'avatar_url' => 'https://crafatar.com/avatars/52ccf93b-9231-4564-aad7-64deb7dd739a?size=96&overlay',
                'last_seen_at' => now()->subMinutes(4)->toIso8601String(),
            ],
            [
                'id' => 'hytale:river',
                'name' => 'RiverMancer',
                'uuid' => '3f8f01d1-0a40-4bec-9e00-5309bc5000fe',
                'source_id' => 'hytale:3f8f01d1-0a40-4bec-9e00-5309bc5000fe',
                'status' => 'online',
                'ping' => 52,
                'role' => 'player',
                'country' => 'SG',
                'avatar_url' => 'https://crafatar.com/avatars/3f8f01d1-0a40-4bec-9e00-5309bc5000fe?size=96&overlay',
                'last_seen_at' => now()->subMinutes(1)->toIso8601String(),
            ],
        ];
    }

    protected function notes(Server $server): array
    {
        return [
            'Hytale provider is currently basic-only as requested.',
            'Avatar source follows Crafatar style for consistency.',
        ];
    }

    protected function tabs(Server $server): array
    {
        return ['overview', 'statistics'];
    }

    protected function actionGroups(Server $server): array
    {
        return parent::actionGroups($server);
    }

    public function statistics(Server $server, string $playerId): array
    {
        if (!$this->profile($server, $playerId)) {
            return parent::statistics($server, $playerId);
        }

        return [
            'available' => true,
            'categories' => [
                [
                    'id' => 'session',
                    'title' => 'Session',
                    'entries' => [
                        ['label' => 'Connected For', 'value' => '1h 09m'],
                        ['label' => 'Region', 'value' => 'Overworld Alpha'],
                        ['label' => 'Deaths', 'value' => '0'],
                    ],
                ],
            ],
            'is_dummy' => true,
            'player_id' => $playerId,
        ];
    }

    protected function integrations(Server $server): array
    {
        return [
            'avatar_source' => 'crafatar',
            'bridge' => 'basic-dummy',
        ];
    }
}
