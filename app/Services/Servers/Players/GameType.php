<?php

namespace Pterodactyl\Services\Servers\Players;

final class GameType
{
    public const MINECRAFT_JAVA = 'minecraft_java';
    public const MINECRAFT_BEDROCK = 'minecraft_bedrock';
    public const FIVEM = 'fivem';
    public const TERRARIA = 'terraria';
    public const PROJECT_ZOMBOID = 'project_zomboid';
    public const HYTALE = 'hytale';
    public const GENERIC = 'generic';

    public static function all(): array
    {
        return [
            self::MINECRAFT_JAVA,
            self::MINECRAFT_BEDROCK,
            self::FIVEM,
            self::TERRARIA,
            self::PROJECT_ZOMBOID,
            self::HYTALE,
            self::GENERIC,
        ];
    }

    public static function label(string $type): string
    {
        return match ($type) {
            self::MINECRAFT_JAVA => 'Minecraft Java',
            self::MINECRAFT_BEDROCK => 'Minecraft Bedrock',
            self::FIVEM => 'FiveM',
            self::TERRARIA => 'Terraria',
            self::PROJECT_ZOMBOID => 'Project Zomboid',
            self::HYTALE => 'Hytale',
            default => 'Generic Game Server',
        };
    }
}
