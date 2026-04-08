<?php

namespace Pterodactyl\Services\Servers\Players\Providers;

use Pterodactyl\Models\Server;
use Pterodactyl\Services\Servers\Players\GameType;

class ProjectZomboidDummyPlayerProvider extends AbstractDummyPlayerProvider
{
    public function gameType(): string
    {
        return GameType::PROJECT_ZOMBOID;
    }

    public function gameLabel(): string
    {
        return GameType::label(GameType::PROJECT_ZOMBOID);
    }

    protected function rawPlayers(Server $server): array
    {
        return [
            [
                'id' => 'pz:naim',
                'name' => 'NaimSurvivor',
                'uuid' => 'steam:1100001498a77ba',
                'source_id' => 'steam:1100001498a77ba',
                'status' => 'online',
                'ping' => 67,
                'role' => 'admin',
                'country' => 'MY',
                'avatar_url' => 'https://api.dicebear.com/9.x/adventurer/svg?seed=NaimSurvivor',
                'last_seen_at' => now()->subMinutes(6)->toIso8601String(),
            ],
            [
                'id' => 'pz:coral',
                'name' => 'CoralWalker',
                'uuid' => 'steam:110000134f7a502',
                'source_id' => 'steam:110000134f7a502',
                'status' => 'online',
                'ping' => 74,
                'role' => 'player',
                'country' => 'SG',
                'avatar_url' => 'https://api.dicebear.com/9.x/adventurer/svg?seed=CoralWalker',
                'last_seen_at' => now()->subMinutes(2)->toIso8601String(),
            ],
            [
                'id' => 'pz:quant',
                'name' => 'Quantizen',
                'uuid' => 'steam:11000011104fd92',
                'source_id' => 'steam:11000011104fd92',
                'status' => 'offline',
                'ping' => 0,
                'role' => 'moderator',
                'country' => 'ID',
                'avatar_url' => 'https://api.dicebear.com/9.x/adventurer/svg?seed=Quantizen',
                'last_seen_at' => now()->subHours(10)->toIso8601String(),
            ],
        ];
    }

    protected function actionGroups(Server $server): array
    {
        return [
            ...parent::actionGroups($server),
            [
                'id' => 'zomboid',
                'title' => 'Project Zomboid Actions',
                'description' => 'RCON-style gameplay controls (dummy).',
                'actions' => [
                    [
                        'id' => 'zomboid.safehouse',
                        'label' => 'Teleport Safehouse',
                        'description' => 'Teleport player to configured safehouse.',
                        'tone' => 'primary',
                        'command' => 'teleport {{player}} safehouse',
                    ],
                    [
                        'id' => 'zomboid.set-access',
                        'label' => 'Set Access',
                        'description' => 'Change player access level.',
                        'tone' => 'neutral',
                        'command' => 'setaccesslevel {{player}} {{level}}',
                        'requires_input' => true,
                        'input_key' => 'level',
                        'input_label' => 'Access Level',
                        'input_placeholder' => 'admin|moderator|none',
                    ],
                    [
                        'id' => 'zomboid.invisible',
                        'label' => 'Toggle Invisible',
                        'description' => 'Toggle invisibility for moderation.',
                        'tone' => 'warning',
                        'command' => 'invisible {{player}}',
                    ],
                ],
            ],
        ];
    }

    protected function integrations(Server $server): array
    {
        return [
            'supported_bridge' => 'rcon',
            'mod_api' => false,
            'bridge' => 'dummy',
        ];
    }

    protected function notes(Server $server): array
    {
        return [
            'Project Zomboid provider is configured for RCON-style operations.',
            'No mod integration is assumed in this dummy stage.',
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
                    'id' => 'survival',
                    'title' => 'Survival',
                    'entries' => [
                        ['label' => 'Survived Days', 'value' => '14'],
                        ['label' => 'Zombie Kills', 'value' => '327'],
                        ['label' => 'Weight', 'value' => '77.2'],
                        ['label' => 'Body Temperature', 'value' => '36.6°C'],
                    ],
                ],
                [
                    'id' => 'session',
                    'title' => 'Session',
                    'entries' => [
                        ['label' => 'Connected For', 'value' => '3h 11m'],
                        ['label' => 'Current Zone', 'value' => 'Muldraugh'],
                    ],
                ],
            ],
            'is_dummy' => true,
            'player_id' => $playerId,
        ];
    }
}
