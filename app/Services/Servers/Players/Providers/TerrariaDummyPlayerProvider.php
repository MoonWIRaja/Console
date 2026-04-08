<?php

namespace Pterodactyl\Services\Servers\Players\Providers;

use Illuminate\Support\Str;
use Pterodactyl\Models\Server;
use Pterodactyl\Services\Servers\Players\GameType;

class TerrariaDummyPlayerProvider extends AbstractDummyPlayerProvider
{
    public function gameType(): string
    {
        return GameType::TERRARIA;
    }

    public function gameLabel(): string
    {
        return GameType::label(GameType::TERRARIA);
    }

    protected function rawPlayers(Server $server): array
    {
        return [
            [
                'id' => 'terraria:naim',
                'name' => 'NaimBuilder',
                'uuid' => 'steam:11000011bc9912d',
                'source_id' => 'steam:11000011bc9912d',
                'status' => 'online',
                'ping' => 58,
                'role' => 'operator',
                'country' => 'MY',
                'avatar_url' => 'https://api.dicebear.com/9.x/bottts/svg?seed=NaimBuilder',
                'last_seen_at' => now()->subMinutes(5)->toIso8601String(),
            ],
            [
                'id' => 'terraria:moon',
                'name' => 'MoonWIRaja',
                'uuid' => 'steam:11000012f90e11f',
                'source_id' => 'steam:11000012f90e11f',
                'status' => 'online',
                'ping' => 63,
                'role' => 'admin',
                'country' => 'MY',
                'avatar_url' => 'https://api.dicebear.com/9.x/bottts/svg?seed=MoonWIRaja',
                'last_seen_at' => now()->subMinutes(1)->toIso8601String(),
            ],
            [
                'id' => 'terraria:delta',
                'name' => 'DeltaTide',
                'uuid' => 'steam:11000014bc99ef4',
                'source_id' => 'steam:11000014bc99ef4',
                'status' => 'offline',
                'ping' => 0,
                'role' => 'player',
                'country' => 'ID',
                'avatar_url' => 'https://api.dicebear.com/9.x/bottts/svg?seed=DeltaTide',
                'last_seen_at' => now()->subHours(4)->toIso8601String(),
            ],
        ];
    }

    protected function actionGroups(Server $server): array
    {
        return [
            ...parent::actionGroups($server),
            [
                'id' => 'terraria',
                'title' => 'Terraria Actions',
                'description' => 'TShock and Terraria server actions (dummy).',
                'actions' => [
                    [
                        'id' => 'terraria.heal',
                        'label' => 'Heal',
                        'description' => 'Restore player HP and mana.',
                        'tone' => 'success',
                        'command' => '/heal {{player}}',
                    ],
                    [
                        'id' => 'terraria.item',
                        'label' => 'Give Item',
                        'description' => 'Give item with amount.',
                        'tone' => 'primary',
                        'command' => '/give {{player}} {{item}} {{amount}}',
                        'requires_input' => true,
                        'input_key' => 'item',
                        'input_label' => 'Item',
                        'input_placeholder' => 'CopperPickaxe 1',
                    ],
                    [
                        'id' => 'terraria.tp',
                        'label' => 'Teleport',
                        'description' => 'Teleport player to spawn.',
                        'tone' => 'neutral',
                        'command' => '/tp {{player}} 0 0',
                    ],
                ],
            ],
        ];
    }

    protected function integrations(Server $server): array
    {
        return [
            'active_mode' => $this->activeMode($server),
            'supported_modes' => ['tshock', 'vanilla', 'tmodloader'],
            'bridge' => 'dummy',
        ];
    }

    protected function notes(Server $server): array
    {
        return [
            'Terraria mode auto-detected from startup/image labels.',
            'Supported mode list is prepared for TShock, Vanilla, and tModLoader.',
        ];
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
                    'id' => 'progress',
                    'title' => 'Progress',
                    'entries' => [
                        ['label' => 'Life Crystals Used', 'value' => '13'],
                        ['label' => 'Mana Stars Used', 'value' => '7'],
                        ['label' => 'Bosses Defeated', 'value' => '5'],
                    ],
                ],
                [
                    'id' => 'session',
                    'title' => 'Session',
                    'entries' => [
                        ['label' => 'Current Biome', 'value' => 'Underground Jungle'],
                        ['label' => 'Session Duration', 'value' => '2h 08m'],
                    ],
                ],
            ],
            'is_dummy' => true,
            'player_id' => $playerId,
        ];
    }

    private function activeMode(Server $server): string
    {
        $server->loadMissing('egg');

        $haystack = Str::lower(implode(' ', array_filter([
            $server->startup,
            $server->image,
            $server->egg?->name,
            $server->egg?->description,
        ])));

        if (Str::contains($haystack, ['tshock'])) {
            return 'tshock';
        }

        if (Str::contains($haystack, ['tmodloader', 'tmod'])) {
            return 'tmodloader';
        }

        return 'vanilla';
    }
}
