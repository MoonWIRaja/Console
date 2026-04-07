<?php

namespace Pterodactyl\Services\Servers\Players\Support;

use Illuminate\Support\Str;
use Pterodactyl\Models\Server;
use Pterodactyl\Services\Servers\Players\GameType;

class PlayerGameTypeResolver
{
    public function resolve(Server $server): string
    {
        $server->loadMissing(['egg', 'nest']);

        $haystack = Str::lower(implode(' ', array_filter([
            $server->name,
            $server->startup,
            $server->image,
            $server->egg?->name,
            $server->egg?->description,
            $server->nest?->name,
        ])));

        // ARK: Check first as it may contain "minecraft" in startup args for mod loaders
        if (Str::contains($haystack, ['ark', 'arkserver', 'ark survival', 'shootergame'])) {
            return GameType::ARK;
        }

        // Minecraft Bedrock: Must check BEFORE Minecraft Java
        if (Str::contains($haystack, [
            'bedrock',
            'pocketmine',
            'nukkit',
            'powernukkit',
            'bedrockdedicatedserver',
            'bdcs',
            'mcpe',
            'mcbe',
        ])) {
            return GameType::MINECRAFT_BEDROCK;
        }

        // FiveM
        if (Str::contains($haystack, ['fivem', 'fxserver', 'txadmin', 'citizenfx'])) {
            return GameType::FIVEM;
        }

        // Terraria
        if (Str::contains($haystack, ['terraria', 'tshock', 'tmodloader', 'tmod'])) {
            return GameType::TERRARIA;
        }

        // Project Zomboid
        if (Str::contains($haystack, ['zomboid', 'project zomboid', 'pzserver'])) {
            return GameType::PROJECT_ZOMBOID;
        }

        // Hytale
        if (Str::contains($haystack, ['hytale'])) {
            return GameType::HYTALE;
        }

        // Minecraft Java: Only match if NOT bedrock keywords are present
        if (Str::contains($haystack, [
            'paper',
            'spigot',
            'purpur',
            'forge',
            'fabric',
            'quilt',
            'velocity',
            'bungeecord',
            'waterfall',
            'vanilla minecraft',
            'minecraft java',
            'mc java',
        ])) {
            return GameType::MINECRAFT_JAVA;
        }

        // Generic "minecraft" without qualifiers - check for Java-specific Docker images
        if (Str::contains($haystack, ['minecraft'])) {
            // If it has bedrock keywords, it's bedrock
            if (Str::contains($haystack, ['bedrock', 'pocketmine', 'nukkit', 'mcpe', 'mcbe'])) {
                return GameType::MINECRAFT_BEDROCK;
            }
            // Default to Java if it just says "minecraft"
            return GameType::MINECRAFT_JAVA;
        }

        return GameType::GENERIC;
    }
}
