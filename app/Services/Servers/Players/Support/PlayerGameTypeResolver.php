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
        if ($this->containsWord($haystack, ['ark', 'arkserver', 'ark survival', 'shootergame'])) {
            return GameType::ARK;
        }

        // Palworld
        if ($this->containsWord($haystack, ['palworld', 'palserver'])) {
            return GameType::PALWORLD;
        }

        // Minecraft Bedrock: Must check BEFORE Minecraft Java
        if ($this->containsWord($haystack, [
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
        if ($this->containsWord($haystack, ['fivem', 'fxserver', 'txadmin', 'citizenfx'])) {
            return GameType::FIVEM;
        }

        // Terraria
        if ($this->containsWord($haystack, ['terraria', 'tshock', 'tmodloader', 'tmod'])) {
            return GameType::TERRARIA;
        }

        // Project Zomboid
        if ($this->containsWord($haystack, ['zomboid', 'project zomboid', 'pzserver'])) {
            return GameType::PROJECT_ZOMBOID;
        }

        // Hytale
        if ($this->containsWord($haystack, ['hytale'])) {
            return GameType::HYTALE;
        }

        // Minecraft Java: Only match if NOT bedrock keywords are present
        if ($this->containsWord($haystack, [
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
        if ($this->containsWord($haystack, ['minecraft'])) {
            // If it has bedrock keywords, it's bedrock
            if ($this->containsWord($haystack, ['bedrock', 'pocketmine', 'nukkit', 'mcpe', 'mcbe'])) {
                return GameType::MINECRAFT_BEDROCK;
            }
            // Default to Java if it just says "minecraft"
            return GameType::MINECRAFT_JAVA;
        }

        return GameType::GENERIC;
    }

    /**
     * Word-boundary keyword match instead of a raw substring check - a bare
     * Str::contains($haystack, ['ark']) matches "ark" inside completely unrelated
     * text (most notably "parkervcp", the Docker image namespace behind a huge
     * share of community eggs across many different games), which was
     * misclassifying Project Zomboid and Terraria servers as ARK. \b anchors each
     * keyword to real word edges instead, including multi-word phrases like
     * "ark survival".
     *
     * @param string[] $words
     */
    private function containsWord(string $haystack, array $words): bool
    {
        foreach ($words as $word) {
            if (preg_match('/\b' . preg_quote($word, '/') . '\b/i', $haystack) === 1) {
                return true;
            }
        }

        return false;
    }
}
