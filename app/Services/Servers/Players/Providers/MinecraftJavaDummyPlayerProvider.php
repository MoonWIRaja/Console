<?php

namespace Pterodactyl\Services\Servers\Players\Providers;

use Pterodactyl\Models\Server;
use Pterodactyl\Services\Servers\Players\GameType;

class MinecraftJavaDummyPlayerProvider extends AbstractDummyPlayerProvider
{
    public function gameType(): string
    {
        return GameType::MINECRAFT_JAVA;
    }

    public function gameLabel(): string
    {
        return GameType::label(GameType::MINECRAFT_JAVA);
    }

    protected function rawPlayers(Server $server): array
    {
        return [
            [
                'id' => '98e8ff49-697e-38fb-9f66-bf3b4c84c9c7',
                'name' => 'Blackdigits',
                'uuid' => '98e8ff49-697e-38fb-9f66-bf3b4c84c9c7',
                'source_id' => 'mcjava:98e8ff49-697e-38fb-9f66-bf3b4c84c9c7',
                'status' => 'online',
                'ping' => 32,
                'role' => 'operator',
                'country' => 'MY',
                'avatar_url' => 'https://crafatar.com/avatars/98e8ff49-697e-38fb-9f66-bf3b4c84c9c7?size=96&overlay',
                'last_seen_at' => now()->subMinutes(2)->toIso8601String(),
            ],
            [
                'id' => 'c9f48942-83eb-43d2-bca5-98d980af3acb',
                'name' => 'Coral',
                'uuid' => 'c9f48942-83eb-43d2-bca5-98d980af3acb',
                'source_id' => 'mcjava:c9f48942-83eb-43d2-bca5-98d980af3acb',
                'status' => 'online',
                'ping' => 45,
                'role' => 'player',
                'country' => 'SG',
                'avatar_url' => 'https://crafatar.com/avatars/c9f48942-83eb-43d2-bca5-98d980af3acb?size=96&overlay',
                'last_seen_at' => now()->subMinutes(1)->toIso8601String(),
            ],
            [
                'id' => '4ea7f8ee-e148-4d4b-aebf-f16f30331fff',
                'name' => 'Izzxt',
                'uuid' => '4ea7f8ee-e148-4d4b-aebf-f16f30331fff',
                'source_id' => 'mcjava:4ea7f8ee-e148-4d4b-aebf-f16f30331fff',
                'status' => 'online',
                'ping' => 28,
                'role' => 'admin',
                'country' => 'ID',
                'avatar_url' => 'https://crafatar.com/avatars/4ea7f8ee-e148-4d4b-aebf-f16f30331fff?size=96&overlay',
                'last_seen_at' => now()->subMinutes(3)->toIso8601String(),
            ],
            [
                'id' => '34f3918a-94d0-4a84-aae6-55d5cbf2f4f2',
                'name' => 'Quantizen',
                'uuid' => '34f3918a-94d0-4a84-aae6-55d5cbf2f4f2',
                'source_id' => 'mcjava:34f3918a-94d0-4a84-aae6-55d5cbf2f4f2',
                'status' => 'offline',
                'ping' => 0,
                'role' => 'moderator',
                'country' => 'US',
                'avatar_url' => 'https://crafatar.com/avatars/34f3918a-94d0-4a84-aae6-55d5cbf2f4f2?size=96&overlay',
                'last_seen_at' => now()->subHours(2)->toIso8601String(),
            ],
            [
                'id' => '896f1ae2-8e4f-41b7-b9d8-f979f409775f',
                'name' => 'Nxim',
                'uuid' => '896f1ae2-8e4f-41b7-b9d8-f979f409775f',
                'source_id' => 'mcjava:896f1ae2-8e4f-41b7-b9d8-f979f409775f',
                'status' => 'offline',
                'ping' => 0,
                'role' => 'player',
                'banned' => true,
                'country' => 'MY',
                'avatar_url' => 'https://crafatar.com/avatars/896f1ae2-8e4f-41b7-b9d8-f979f409775f?size=96&overlay',
                'last_seen_at' => now()->subDays(1)->toIso8601String(),
            ],
        ];
    }

    protected function actionGroups(Server $server): array
    {
        return [
            ...parent::actionGroups($server),
            [
                'id' => 'minecraft',
                'title' => 'Minecraft Actions',
                'description' => 'Quick gameplay tools for Java servers.',
                'actions' => [
                    [
                        'id' => 'minecraft.gamemode',
                        'label' => 'Gamemode',
                        'description' => 'Switch player gamemode.',
                        'tone' => 'success',
                        'command' => 'gamemode {{mode}} {{player}}',
                        'requires_input' => true,
                        'input_key' => 'mode',
                        'input_label' => 'Mode',
                        'input_placeholder' => 'survival|creative|adventure|spectator',
                    ],
                    [
                        'id' => 'minecraft.heal',
                        'label' => 'Heal',
                        'description' => 'Restore health immediately.',
                        'tone' => 'success',
                        'command' => 'effect give {{player}} minecraft:instant_health 1 1 true',
                    ],
                    [
                        'id' => 'minecraft.kill',
                        'label' => 'Kill',
                        'description' => 'Force player death command.',
                        'tone' => 'danger',
                        'command' => 'kill {{player}}',
                    ],
                    [
                        'id' => 'minecraft.effect',
                        'label' => 'Effect',
                        'description' => 'Apply potion effect.',
                        'tone' => 'neutral',
                        'command' => 'effect give {{player}} {{effect}} 60 1 true',
                        'requires_input' => true,
                        'input_key' => 'effect',
                        'input_label' => 'Effect ID',
                        'input_placeholder' => 'minecraft:speed',
                    ],
                ],
            ],
            [
                'id' => 'inventory',
                'title' => 'Inventory Management',
                'description' => 'Inventory-related commands.',
                'actions' => [
                    [
                        'id' => 'inventory.give',
                        'label' => 'Give Item',
                        'description' => 'Give item to player inventory.',
                        'tone' => 'primary',
                        'command' => 'give {{player}} {{item}} {{amount}}',
                        'requires_input' => true,
                        'input_key' => 'item',
                        'input_label' => 'Item ID',
                        'input_placeholder' => 'minecraft:diamond 1',
                    ],
                    [
                        'id' => 'inventory.clear',
                        'label' => 'Clear Inventory',
                        'description' => 'Remove all carried items.',
                        'tone' => 'warning',
                        'command' => 'clear {{player}}',
                    ],
                    [
                        'id' => 'inventory.enderchest',
                        'label' => 'Inspect Ender Chest',
                        'description' => 'Open remote ender chest view (dummy).',
                        'tone' => 'neutral',
                        'command' => 'data get entity {{player}} EnderItems',
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
            'view_modes' => ['inventory', 'ender_chest'],
            'sections' => [
                [
                    'id' => 'armor',
                    'title' => 'Armor',
                    'slots' => [
                        ['slot' => 'helmet', 'item_name' => 'Netherite Helmet', 'item_id' => 'minecraft:netherite_helmet', 'count' => 1, 'icon_url' => 'https://mc-heads.net/item/netherite_helmet'],
                        ['slot' => 'chestplate', 'item_name' => 'Diamond Chestplate', 'item_id' => 'minecraft:diamond_chestplate', 'count' => 1, 'icon_url' => 'https://mc-heads.net/item/diamond_chestplate'],
                        ['slot' => 'leggings', 'item_name' => 'Iron Leggings', 'item_id' => 'minecraft:iron_leggings', 'count' => 1, 'icon_url' => 'https://mc-heads.net/item/iron_leggings'],
                        ['slot' => 'boots', 'item_name' => 'Golden Boots', 'item_id' => 'minecraft:golden_boots', 'count' => 1, 'icon_url' => 'https://mc-heads.net/item/golden_boots'],
                    ],
                ],
                [
                    'id' => 'hotbar',
                    'title' => 'Hotbar',
                    'slots' => [
                        ['slot' => '1', 'item_name' => 'Netherite Sword', 'item_id' => 'minecraft:netherite_sword', 'count' => 1, 'icon_url' => 'https://mc-heads.net/item/netherite_sword'],
                        ['slot' => '2', 'item_name' => 'Steak', 'item_id' => 'minecraft:cooked_beef', 'count' => 42, 'icon_url' => 'https://mc-heads.net/item/cooked_beef'],
                        ['slot' => '3', 'item_name' => 'Torch', 'item_id' => 'minecraft:torch', 'count' => 64, 'icon_url' => 'https://mc-heads.net/item/torch'],
                    ],
                ],
                [
                    'id' => 'inventory',
                    'title' => 'Main Inventory',
                    'slots' => [
                        ['slot' => '12', 'item_name' => 'Oak Planks', 'item_id' => 'minecraft:oak_planks', 'count' => 16, 'icon_url' => 'https://mc-heads.net/item/oak_planks'],
                        ['slot' => '13', 'item_name' => 'Amethyst Shard', 'item_id' => 'minecraft:amethyst_shard', 'count' => 12, 'icon_url' => 'https://mc-heads.net/item/amethyst_shard'],
                        ['slot' => '27', 'item_name' => 'Elytra', 'item_id' => 'minecraft:elytra', 'count' => 1, 'icon_url' => 'https://mc-heads.net/item/elytra'],
                    ],
                ],
            ],
            'summary' => [
                ['label' => 'Total Items', 'value' => '138'],
                ['label' => 'Netherite Count', 'value' => '2'],
                ['label' => 'Inventory Size', 'value' => '36 + Armor + Offhand'],
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
                    'id' => 'activity',
                    'title' => 'Activity',
                    'entries' => [
                        ['label' => 'Play Time', 'value' => '5h 53m'],
                        ['label' => 'Time Since Rest', 'value' => '49m'],
                        ['label' => 'Time Since Death', 'value' => '39m'],
                        ['label' => 'Distance Flown', 'value' => '74.3m'],
                    ],
                ],
                [
                    'id' => 'usage',
                    'title' => 'Items Used',
                    'entries' => [
                        ['label' => 'Netherite Pickaxe', 'value' => '27'],
                        ['label' => 'Oak Wood', 'value' => '11'],
                        ['label' => 'Oak Stairs', 'value' => '10'],
                        ['label' => 'Netherite Sword', 'value' => '8'],
                    ],
                ],
                [
                    'id' => 'combat',
                    'title' => 'Combat & Deaths',
                    'entries' => [
                        ['label' => 'Skeleton Deaths', 'value' => '2'],
                        ['label' => 'Phantom Deaths', 'value' => '1'],
                        ['label' => 'PvP Kills', 'value' => '4'],
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
            'avatar_base_url' => 'https://crafatar.com/avatars/{uuid}?size=96&overlay',
            'query_bridge' => 'dummy',
        ];
    }

    protected function notes(Server $server): array
    {
        return [
            'Avatar source is set to Crafatar for Minecraft profiles.',
            'Actions and inventory data are dummy responses until live bridge is wired.',
        ];
    }
}
