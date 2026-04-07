<?php

namespace Pterodactyl\Services\Servers\Players\Providers;

use Pterodactyl\Models\Server;
use Pterodactyl\Services\Servers\Players\GameType;

class ArkDummyPlayerProvider extends AbstractDummyPlayerProvider
{
    public function gameType(): string
    {
        return GameType::ARK;
    }

    public function gameLabel(): string
    {
        return GameType::label(GameType::ARK);
    }

    protected function rawPlayers(Server $server): array
    {
        return [
            [
                'id' => '1234567890',
                'name' => 'DinoTamer99',
                'uuid' => 'steam_76561198000000001',
                'source_id' => 'steam:76561198000000001',
                'status' => 'online',
                'ping' => 45,
                'role' => 'admin',
                'country' => 'MY',
                'avatar_url' => 'https://api.dicebear.com/9.x/identicon/svg?seed=DinoTamer99',
                'last_seen_at' => now()->subMinutes(1)->toIso8601String(),
            ],
            [
                'id' => '9876543210',
                'name' => 'RexRider',
                'uuid' => 'steam_76561198000000002',
                'source_id' => 'steam:76561198000000002',
                'status' => 'online',
                'ping' => 62,
                'role' => 'player',
                'country' => 'SG',
                'avatar_url' => 'https://api.dicebear.com/9.x/identicon/svg?seed=RexRider',
                'last_seen_at' => now()->subMinutes(3)->toIso8601String(),
            ],
            [
                'id' => '5555555555',
                'name' => 'TribeChief',
                'uuid' => 'steam_76561198000000003',
                'source_id' => 'steam:76561198000000003',
                'status' => 'offline',
                'ping' => 0,
                'role' => 'moderator',
                'country' => 'TH',
                'avatar_url' => 'https://api.dicebear.com/9.x/identicon/svg?seed=TribeChief',
                'last_seen_at' => now()->subHours(4)->toIso8601String(),
            ],
        ];
    }

    protected function actionGroups(Server $server): array
    {
        return [
            ...parent::actionGroups($server),
            [
                'id' => 'ark',
                'title' => 'ARK Actions',
                'description' => 'ARK: Survival Evolved specific admin actions.',
                'actions' => [
                    [
                        'id' => 'ark.giveitems',
                        'label' => 'Give Items',
                        'description' => 'Give resources to player inventory.',
                        'tone' => 'primary',
                        'command' => 'GiveItemToPlayer {{player}} {{item}} {{amount}}',
                        'requires_input' => true,
                        'input_key' => 'item',
                        'input_label' => 'Item Blueprint Path',
                        'input_placeholder' => 'Blueprint\'/Game/PrimalEarth/CoreBlueprints/Weapons/PrimalItem_WeaponSword.PrimalItem_WeaponSword\'',
                    ],
                    [
                        'id' => 'ark.teleport',
                        'label' => 'Teleport To Player',
                        'description' => 'Teleport to player location.',
                        'tone' => 'neutral',
                        'command' => 'TeleportToPlayer {{player}}',
                    ],
                    [
                        'id' => 'ark.setplayerpos',
                        'label' => 'Set Player Position',
                        'description' => 'Teleport player to coordinates.',
                        'tone' => 'neutral',
                        'command' => 'SetPlayerPos {{player}} {{x}} {{y}} {{z}}',
                        'requires_input' => true,
                        'input_key' => 'coords',
                        'input_label' => 'X Y Z Coordinates',
                        'input_placeholder' => '10000 500 10000',
                    ],
                ],
            ],
        ];
    }

    public function inventory(Server $server, string $playerId): array
    {
        if (!$this->profile($server, $playerId)) {
            return parent::inventory($server, $playerId);
        }

        return [
            'available' => true,
            'sections' => [
                [
                    'id' => 'inventory',
                    'title' => 'Inventory',
                    'slots' => [
                        ['slot' => '1', 'item_name' => 'Metal Pick', 'item_id' => 'PrimalItem_WeaponPickaxe', 'count' => 1],
                        ['slot' => '2', 'item_name' => 'Raw Meat', 'item_id' => 'PrimalItemResource_Meat', 'count' => 50],
                        ['slot' => '3', 'item_name' => 'Wood', 'item_id' => 'PrimalItemResource_Wood', 'count' => 200],
                    ],
                ],
                [
                    'id' => 'equipped',
                    'title' => 'Equipped',
                    'slots' => [
                        ['slot' => 'Helmet', 'item_name' => 'Flak Helmet', 'item_id' => 'PrimalItemArmor_FlakHelmet', 'count' => 1],
                        ['slot' => 'Chest', 'item_name' => 'Flak Chestpiece', 'item_id' => 'PrimalItemArmor_FlakShirt', 'count' => 1],
                    ],
                ],
            ],
            'summary' => [
                ['label' => 'Weight', 'value' => '124 / 350'],
                ['label' => 'Slots Used', 'value' => '38 / 100'],
            ],
            'is_dummy' => true,
            'player_id' => $playerId,
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
                    'id' => 'ark-survivor',
                    'title' => 'Survivor Stats',
                    'entries' => [
                        ['label' => 'Level', 'value' => '87'],
                        ['label' => 'Tribe', 'value' => 'Alpha Squad'],
                        ['label' => 'Playtime', 'value' => '142h 30m'],
                    ],
                ],
                [
                    'id' => 'ark-stats',
                    'title' => 'Stats',
                    'entries' => [
                        ['label' => 'Health', 'value' => '12,400'],
                        ['label' => 'Stamina', 'value' => '840'],
                        ['label' => 'Weight', 'value' => '350'],
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
            'avatar_source' => 'dicebear',
            'bridge' => 'ark-rcon-dummy',
        ];
    }
}
