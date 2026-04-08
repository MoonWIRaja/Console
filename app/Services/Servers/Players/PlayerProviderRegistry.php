<?php

namespace Pterodactyl\Services\Servers\Players;

use Throwable;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;
use Pterodactyl\Repositories\Wings\DaemonCommandRepository;
use Pterodactyl\Services\Servers\Players\Contracts\PlayerProviderInterface;
use Pterodactyl\Services\Servers\Players\Providers\MinecraftJavaLivePlayerProvider;
use Pterodactyl\Services\Servers\Players\Providers\MinecraftBedrockLivePlayerProvider;
use Pterodactyl\Services\Servers\Players\Providers\FiveMLivePlayerProvider;
use Pterodactyl\Services\Servers\Players\Providers\ProjectZomboidLivePlayerProvider;
use Pterodactyl\Services\Servers\Players\Providers\TerrariaLivePlayerProvider;
use Pterodactyl\Services\Servers\Players\Providers\ArkLivePlayerProvider;
use Pterodactyl\Services\Servers\Players\Providers\HytaleLivePlayerProvider;
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

        try {
            return match ($resolved) {
                GameType::MINECRAFT_JAVA => new MinecraftJavaLivePlayerProvider(
                    $this->fileRepository,
                    $this->commandRepository
                ),
                GameType::MINECRAFT_BEDROCK => new MinecraftBedrockLivePlayerProvider(
                    $this->fileRepository,
                    $this->commandRepository
                ),
                GameType::FIVEM => new FiveMLivePlayerProvider(
                    $this->fileRepository,
                    $this->commandRepository
                ),
                GameType::PROJECT_ZOMBOID => new ProjectZomboidLivePlayerProvider(
                    $this->fileRepository,
                    $this->commandRepository
                ),
                GameType::TERRARIA => new TerrariaLivePlayerProvider(
                    $this->fileRepository,
                    $this->commandRepository
                ),
                GameType::ARK => new ArkLivePlayerProvider(
                    $this->fileRepository,
                    $this->commandRepository
                ),
                GameType::HYTALE => new HytaleLivePlayerProvider(
                    $this->fileRepository,
                    $this->commandRepository
                ),
                default => new UnavailablePlayerProvider($resolved),
            };
        } catch (Throwable $exception) {
            Log::error('Failed to resolve live player provider, falling back to unavailable provider.', [
                'game_type' => $resolved,
                'error' => $exception->getMessage(),
            ]);

            return new UnavailablePlayerProvider($resolved);
        }
    }
}
