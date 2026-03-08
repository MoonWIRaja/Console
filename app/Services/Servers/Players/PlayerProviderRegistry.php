<?php

namespace Pterodactyl\Services\Servers\Players;

use Pterodactyl\Repositories\Wings\DaemonFileRepository;
use Pterodactyl\Repositories\Wings\DaemonCommandRepository;
use Pterodactyl\Services\Servers\Players\Contracts\PlayerProviderInterface;
use Pterodactyl\Services\Servers\Players\Providers\MinecraftJavaLivePlayerProvider;
use Pterodactyl\Services\Servers\Players\Providers\UnavailablePlayerProvider;

class PlayerProviderRegistry
{
    public function __construct(
        private DaemonFileRepository $fileRepository,
        private DaemonCommandRepository $commandRepository,
    ) {
    }

    public function forType(string $type): PlayerProviderInterface
    {
        $resolved = in_array($type, GameType::all(), true) ? $type : GameType::GENERIC;

        return match ($resolved) {
            GameType::MINECRAFT_JAVA => new MinecraftJavaLivePlayerProvider(
                $this->fileRepository,
                $this->commandRepository
            ),
            default => new UnavailablePlayerProvider($resolved),
        };
    }
}
