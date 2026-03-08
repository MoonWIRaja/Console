<?php

namespace Pterodactyl\Services\Servers\Players\Providers;

use Pterodactyl\Models\Server;
use Pterodactyl\Services\Servers\Players\GameType;

class FiveMDummyPlayerProvider extends AbstractDummyPlayerProvider
{
    public function gameType(): string
    {
        return GameType::FIVEM;
    }

    public function gameLabel(): string
    {
        return GameType::label(GameType::FIVEM);
    }

    protected function rawPlayers(Server $server): array
    {
        return [
            [
                'id' => '14',
                'name' => 'JayMarlow',
                'uuid' => 'license:2dd1936cf3f311eb9f1f0242ac130003',
                'source_id' => 'steam:11000014a1b0d7c',
                'status' => 'online',
                'ping' => 41,
                'role' => 'admin',
                'country' => 'MY',
                'avatar_url' => 'https://api.dicebear.com/9.x/identicon/svg?seed=JayMarlow',
                'last_seen_at' => now()->subMinutes(2)->toIso8601String(),
            ],
            [
                'id' => '28',
                'name' => 'VexNine',
                'uuid' => 'license:ef883ca2da55481f9adf0f6442b39357',
                'source_id' => 'steam:11000013ff22b16',
                'status' => 'online',
                'ping' => 56,
                'role' => 'player',
                'country' => 'SG',
                'avatar_url' => 'https://api.dicebear.com/9.x/identicon/svg?seed=VexNine',
                'last_seen_at' => now()->subMinutes(1)->toIso8601String(),
            ],
            [
                'id' => '32',
                'name' => 'RogueBell',
                'uuid' => 'license:0f8400fd179d49528fd89a6a1eb67d86',
                'source_id' => 'steam:11000011a89ba5c',
                'status' => 'offline',
                'ping' => 0,
                'role' => 'moderator',
                'country' => 'TH',
                'avatar_url' => 'https://api.dicebear.com/9.x/identicon/svg?seed=RogueBell',
                'last_seen_at' => now()->subHours(3)->toIso8601String(),
            ],
        ];
    }

    protected function actionGroups(Server $server): array
    {
        return [
            ...parent::actionGroups($server),
            [
                'id' => 'fivem',
                'title' => 'FiveM Actions',
                'description' => 'Runtime controls for connected FiveM players.',
                'actions' => [
                    [
                        'id' => 'fivem.spectate',
                        'label' => 'Spectate',
                        'description' => 'Attach camera to player (dummy).',
                        'tone' => 'primary',
                        'command' => 'txadmin:spectate {{id}}',
                    ],
                    [
                        'id' => 'fivem.freeze',
                        'label' => 'Freeze',
                        'description' => 'Toggle player movement freeze.',
                        'tone' => 'warning',
                        'command' => 'txadmin:freeze {{id}}',
                    ],
                    [
                        'id' => 'fivem.setjob',
                        'label' => 'Set Job',
                        'description' => 'Assign framework job.',
                        'tone' => 'neutral',
                        'command' => 'setjob {{id}} {{job}}',
                        'requires_input' => true,
                        'input_key' => 'job',
                        'input_label' => 'Job',
                        'input_placeholder' => 'police',
                    ],
                ],
            ],
        ];
    }

    protected function integrations(Server $server): array
    {
        return [
            'recommended_framework' => 'txAdmin',
            'supported_frameworks' => ['txAdmin', 'QBCore', 'ESX', 'Standalone'],
            'bridge' => 'dummy',
        ];
    }

    protected function notes(Server $server): array
    {
        return [
            'Framework coverage is prepared for txAdmin-first setup.',
            'All actions currently return dummy accepted responses.',
        ];
    }

    protected function tabs(Server $server): array
    {
        return ['overview', 'statistics'];
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
                        ['label' => 'Character', 'value' => 'Unknown (dummy)'],
                        ['label' => 'Connected For', 'value' => '46m'],
                        ['label' => 'Current Vehicle', 'value' => 'Sultan RS'],
                    ],
                ],
                [
                    'id' => 'moderation',
                    'title' => 'Moderation',
                    'entries' => [
                        ['label' => 'Warnings', 'value' => '0'],
                        ['label' => 'Kicks (7d)', 'value' => '1'],
                        ['label' => 'Bans (30d)', 'value' => '0'],
                    ],
                ],
            ],
            'is_dummy' => true,
            'player_id' => $playerId,
        ];
    }
}
