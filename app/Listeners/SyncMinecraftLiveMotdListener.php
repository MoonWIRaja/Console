<?php

namespace Pterodactyl\Listeners;

use Throwable;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Events\Server\InstallationCompleted;
use Pterodactyl\Services\AlwaysMotd\LiveMinecraftMotdSyncService;

class SyncMinecraftLiveMotdListener
{
    public function __construct(private LiveMinecraftMotdSyncService $syncService)
    {
    }

    public function handle(InstallationCompleted $event): void
    {
        try {
            $result = $this->syncService->sync($event->server);

            if (($result['status'] ?? 'skipped') === 'synced') {
                Log::info('Live Minecraft MOTD synced after installation completed.', [
                    'server_id' => $event->server->id,
                    'server_uuid' => $event->server->uuid,
                    'game_type' => $result['game_type'] ?? null,
                    'initial_install' => $event->isInitialInstall,
                ]);
            }
        } catch (Throwable $exception) {
            report($exception);

            Log::warning('Failed to sync live Minecraft MOTD after installation completed.', [
                'server_id' => $event->server->id,
                'server_uuid' => $event->server->uuid,
                'initial_install' => $event->isInitialInstall,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
