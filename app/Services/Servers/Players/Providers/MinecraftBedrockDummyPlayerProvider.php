<?php

namespace Pterodactyl\Services\Servers\Players\Providers;

use Pterodactyl\Models\Server;
use Pterodactyl\Services\Servers\Players\GameType;

class MinecraftBedrockDummyPlayerProvider extends AbstractDummyPlayerProvider
{
    public function gameType(): string
    {
        return GameType::MINECRAFT_BEDROCK;
    }

    public function gameLabel(): string
    {
        return GameType::label(GameType::MINECRAFT_BEDROCK);
    }

    protected function rawPlayers(Server $server): array
    {
        return [
            [
                'id' => 'xuid-2533274801122334',
                'name' => 'BedrockNaim',
                'uuid' => 'b0f86fdd-6779-4ac8-9b31-182aaad4fa6f',
                'source_id' => 'xuid:2533274801122334',
                'status' => 'online',
                'ping' => 39,
                'role' => 'operator',
                'country' => 'MY',
                'avatar_url' => 'https://crafatar.com/avatars/b0f86fdd-6779-4ac8-9b31-182aaad4fa6f?size=96&overlay',
                'last_seen_at' => now()->subMinutes(2)->toIso8601String(),
            ],
            [
                'id' => 'xuid-2533274809988776',
                'name' => 'PixelReef',
                'uuid' => 'f44916b6-ac01-4ee2-967c-a8f4f9019dca',
                'source_id' => 'xuid:2533274809988776',
                'status' => 'online',
                'ping' => 54,
                'role' => 'player',
                'country' => 'TH',
                'avatar_url' => 'https://crafatar.com/avatars/f44916b6-ac01-4ee2-967c-a8f4f9019dca?size=96&overlay',
                'last_seen_at' => now()->subMinutes(1)->toIso8601String(),
            ],
            [
                'id' => 'xuid-2533274899123456',
                'name' => 'BdrkSentinel',
                'uuid' => '39f15def-4e7f-48dc-b2a8-7e384d0c5f19',
                'source_id' => 'xuid:2533274899123456',
                'status' => 'offline',
                'ping' => 0,
                'role' => 'admin',
                'country' => 'SG',
                'avatar_url' => 'https://crafatar.com/avatars/39f15def-4e7f-48dc-b2a8-7e384d0c5f19?size=96&overlay',
                'last_seen_at' => now()->subHours(5)->toIso8601String(),
            ],
        ];
    }

    protected function actionGroups(Server $server): array
    {
        return [
            ...parent::actionGroups($server),
            [
                'id' => 'bedrock',
                'title' => 'Bedrock Actions',
                'description' => 'Bedrock-compatible utility actions.',
                'actions' => [
                    [
                        'id' => 'bedrock.form',
                        'label' => 'Send Form',
                        'description' => 'Send server-side UI form (dummy).',
                        'tone' => 'neutral',
                        'command' => 'bd-form {{player}} {{form_id}}',
                        'requires_input' => true,
                        'input_key' => 'form_id',
                        'input_label' => 'Form ID',
                        'input_placeholder' => 'welcome_menu',
                    ],
                    [
                        'id' => 'bedrock.device-info',
                        'label' => 'Device Info',
                        'description' => 'Fetch platform and client details (dummy).',
                        'tone' => 'primary',
                        'command' => 'bd-device {{player}}',
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
                    'id' => 'hotbar',
                    'title' => 'Hotbar',
                    'slots' => [
                        ['slot' => '1', 'item_name' => 'Diamond Sword', 'item_id' => 'minecraft:diamond_sword', 'count' => 1],
                        ['slot' => '2', 'item_name' => 'Golden Apple', 'item_id' => 'minecraft:golden_apple', 'count' => 3],
                        ['slot' => '3', 'item_name' => 'Torch', 'item_id' => 'minecraft:torch', 'count' => 64],
                    ],
                ],
            ],
            'summary' => [
                ['label' => 'Open Slots', 'value' => '21'],
                ['label' => 'Selected Slot', 'value' => '1'],
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
                    'id' => 'bedrock-session',
                    'title' => 'Session',
                    'entries' => [
                        ['label' => 'Platform', 'value' => 'Windows'],
                        ['label' => 'Input Type', 'value' => 'Keyboard & Mouse'],
                        ['label' => 'Session Duration', 'value' => '1h 12m'],
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
            'bridge' => 'bedrock-query-dummy',
        ];
    }
}
