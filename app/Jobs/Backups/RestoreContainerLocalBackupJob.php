<?php

namespace Pterodactyl\Jobs\Backups;

use Throwable;
use Pterodactyl\Models\Backup;
use Pterodactyl\Models\Server;
use Illuminate\Bus\Queueable;
use Pterodactyl\Facades\Activity;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Pterodactyl\Services\Backups\RestoreContainerLocalBackupService;

class RestoreContainerLocalBackupJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(
        public int $serverId,
        public int $backupId,
        public bool $truncate = false,
    ) {
    }

    public function handle(RestoreContainerLocalBackupService $restoreService): void
    {
        $server = Server::query()->find($this->serverId);
        $backup = Backup::query()->find($this->backupId);

        if (!$server || !$backup || (int) $backup->server_id !== (int) $server->id) {
            if ($server) {
                $server->update(['status' => null]);
            }

            return;
        }

        try {
            $restoreService->handle($server, $backup, $this->truncate);
            $server->update(['status' => null]);

            Activity::event('server:backup.restore-complete')
                ->subject($backup, $server)
                ->property('name', $backup->name)
                ->log();
        } catch (Throwable $exception) {
            Log::error('Failed to restore local container backup.', [
                'server_id' => $server->id,
                'backup_id' => $backup->id,
                'backup_uuid' => $backup->uuid,
                'truncate' => $this->truncate,
                'exception' => $exception->getMessage(),
            ]);

            $server->update(['status' => null]);

            Activity::event('server.backup.restore-failed')
                ->subject($backup, $server)
                ->property('name', $backup->name)
                ->log();

            throw $exception;
        }
    }
}

