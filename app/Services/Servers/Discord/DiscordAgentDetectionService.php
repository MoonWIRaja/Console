<?php

namespace Pterodactyl\Services\Servers\Discord;

use Illuminate\Support\Str;
use Pterodactyl\Models\Server;
use Pterodactyl\Services\Servers\Players\GameType;

class DiscordAgentDetectionService
{
    public function detect(Server $server): array
    {
        $server->loadMissing(['egg', 'nest', 'variables']);

        $fingerprint = $this->buildFingerprint($server);
        $haystack = Str::lower(implode(' ', $fingerprint));
        $candidates = [
            GameType::MINECRAFT_BEDROCK => $this->score($haystack, [
                'bedrock' => 28,
                'bedrockdedicatedserver' => 32,
                'pocketmine' => 30,
                'nukkit' => 30,
                'powernukkit' => 30,
                'mcpe' => 24,
                'mcbe' => 24,
                'allowlist.json' => 20,
                'permissions.json' => 16,
            ]),
            GameType::MINECRAFT_JAVA => $this->score($haystack, [
                'minecraft' => 12,
                'minecraft java' => 28,
                'vanilla minecraft' => 26,
                'paper' => 32,
                'spigot' => 32,
                'purpur' => 32,
                'forge' => 30,
                'fabric' => 30,
                'quilt' => 28,
                'bungeecord' => 24,
                'velocity' => 24,
                'server.properties' => 28,
                'ops.json' => 20,
                'whitelist.json' => 20,
                'playerdata' => 18,
                'java ' => 18,
                '.jar' => 16,
            ]),
            GameType::FIVEM => $this->score($haystack, [
                'fivem' => 34,
                'fxserver' => 36,
                'citizenfx' => 34,
                'txadmin' => 26,
                'server.cfg' => 12,
                'fxmanifest.lua' => 22,
            ]),
            GameType::TERRARIA => $this->score($haystack, [
                'terraria' => 36,
                'tshock' => 34,
                'tmodloader' => 30,
                'terrariaserver' => 30,
                '.wld' => 18,
            ]),
            GameType::ARK => $this->score($haystack, [
                'ark' => 30,
                'ark survival' => 38,
                'shootergame' => 38,
                'arkserver' => 34,
                'rconport' => 12,
            ]),
            GameType::PROJECT_ZOMBOID => $this->score($haystack, [
                'project zomboid' => 38,
                'zomboid' => 34,
                'pzserver' => 30,
            ]),
            GameType::HYTALE => $this->score($haystack, [
                'hytale' => 40,
            ]),
        ];

        arsort($candidates);
        $type = array_key_first($candidates);
        $confidence = min(100, (int) ($candidates[$type] ?? 0));

        if ($confidence < 18) {
            $type = GameType::GENERIC;
            $confidence = 8;
        }

        return [
            'detected_game_type' => $type,
            'adapter' => $this->adapterFor($type, $confidence),
            'confidence' => $confidence,
            'sources' => array_values(array_filter(array_keys($fingerprint), fn (string $key) => filled($fingerprint[$key]))),
            'fingerprint' => $fingerprint,
            'capabilities' => $this->capabilitiesFor($type, $confidence),
        ];
    }

    private function buildFingerprint(Server $server): array
    {
        $variables = $server->variables
            ->map(fn ($variable) => implode('=', array_filter([
                $variable->env_variable,
                $variable->name,
                $variable->server_value ?? $variable->default_value,
            ])))
            ->implode(' ');

        return [
            'server_name' => $server->name,
            'startup' => $server->startup,
            'docker_image' => $server->image,
            'egg_name' => $server->egg?->name,
            'egg_description' => $server->egg?->description,
            'egg_startup' => $server->egg?->startup,
            'egg_config_startup' => $server->egg?->config_startup,
            'nest_name' => $server->nest?->name,
            'variables' => $variables,
        ];
    }

    private function score(string $haystack, array $weights): int
    {
        $score = 0;
        foreach ($weights as $needle => $weight) {
            if (Str::contains($haystack, $needle)) {
                $score += $weight;
            }
        }

        return $score;
    }

    private function adapterFor(string $type, int $confidence): string
    {
        if ($confidence < 18) {
            return 'generic_log_agent';
        }

        return match ($type) {
            GameType::MINECRAFT_JAVA => 'minecraft_java_agent',
            GameType::MINECRAFT_BEDROCK => 'minecraft_bedrock_agent',
            GameType::FIVEM => 'fivem_agent',
            GameType::TERRARIA => 'terraria_agent',
            GameType::ARK => 'ark_rcon_agent',
            GameType::PROJECT_ZOMBOID => 'project_zomboid_agent',
            GameType::HYTALE => 'hytale_agent',
            default => 'generic_log_agent',
        };
    }

    private function capabilitiesFor(string $type, int $confidence): array
    {
        $base = [
            'chat_bridge' => false,
            'console_bridge' => false,
            'player_link' => false,
            'whitelist' => false,
            'offline_players' => false,
            'inventory' => false,
            'statistics' => false,
            'rcon' => false,
            'log_events' => $confidence >= 18,
        ];

        return match ($type) {
            GameType::MINECRAFT_JAVA => [
                ...$base,
                'chat_bridge' => true,
                'console_bridge' => true,
                'player_link' => true,
                'whitelist' => true,
                'offline_players' => true,
                'inventory' => true,
                'statistics' => true,
                'rcon' => true,
            ],
            GameType::MINECRAFT_BEDROCK => [
                ...$base,
                'chat_bridge' => true,
                'console_bridge' => true,
                'player_link' => true,
                'whitelist' => true,
                'offline_players' => true,
                'rcon' => true,
            ],
            GameType::FIVEM, GameType::TERRARIA, GameType::ARK, GameType::PROJECT_ZOMBOID => [
                ...$base,
                'chat_bridge' => true,
                'console_bridge' => true,
                'offline_players' => true,
                'rcon' => true,
            ],
            default => $base,
        };
    }
}
