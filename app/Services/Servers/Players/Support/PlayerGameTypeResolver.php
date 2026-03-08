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

        if (Str::contains($haystack, ['bedrock', 'pocketmine', 'nukkit', 'powernukkit'])) {
            return GameType::MINECRAFT_BEDROCK;
        }

        if (Str::contains($haystack, ['fivem', 'five m', 'fxserver', 'txadmin', 'citizenfx'])) {
            return GameType::FIVEM;
        }

        if (Str::contains($haystack, ['terraria', 'tshock', 'tmodloader', 'tmod'])) {
            return GameType::TERRARIA;
        }

        if (Str::contains($haystack, ['zomboid', 'project zomboid'])) {
            return GameType::PROJECT_ZOMBOID;
        }

        if (Str::contains($haystack, ['hytale'])) {
            return GameType::HYTALE;
        }

        if (Str::contains($haystack, [
            'minecraft',
            'paper',
            'spigot',
            'purpur',
            'forge',
            'fabric',
            'quilt',
            'velocity',
            'bungeecord',
            'waterfall',
        ])) {
            return GameType::MINECRAFT_JAVA;
        }

        return GameType::GENERIC;
    }
}
