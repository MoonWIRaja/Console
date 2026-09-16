<?php

namespace Pterodactyl\Services\Servers;

use Webmozart\Assert\Assert;
use Pterodactyl\Models\Server;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Repositories\Wings\DaemonServerRepository;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class SuspensionService
{
    public const ACTION_SUSPEND = 'suspend';
    public const ACTION_UNSUSPEND = 'unsuspend';

    /**
     * SuspensionService constructor.
     */
    public function __construct(
        private DaemonServerRepository $daemonServerRepository,
    ) {
    }

    /**
     * Suspends a server on the system.
     *
     * @throws \Throwable
     */
    public function toggle(Server $server, string $action = self::ACTION_SUSPEND): void
    {
        Assert::oneOf($action, [self::ACTION_SUSPEND, self::ACTION_UNSUSPEND]);

        $isSuspending = $action === self::ACTION_SUSPEND;
        // The database already reflects the requested state, so no status update is
        // needed. Wings can still have drifted out of sync though (e.g. a prior sync
        // that never landed, or a DB-only status change from billing), which leaves a
        // paid-but-still-suspended server stuck. Best-effort reconcile Wings onto the
        // current state so a repeated (un)suspend — such as a renewal — self-heals the
        // drift. We also mirror the state onto any split children.
        if ($isSuspending === $server->isSuspended()) {
            try {
                $this->daemonServerRepository->setServer($server)->sync();
            } catch (\Exception $exception) {
                Log::warning('Failed to reconcile Wings suspension state during no-op toggle.', [
                    'server_id' => $server->id,
                    'action' => $action,
                    'exception' => $exception->getMessage(),
                ]);
            }

            $this->cascadeToSplitFamily($server, $action);

            return;
        }

        // Check if the server is currently being transferred.
        if (!is_null($server->transfer)) {
            throw new ConflictHttpException('Cannot toggle suspension status on a server that is currently being transferred.');
        }

        // Update the server's suspension status.
        $server->update([
            'status' => $isSuspending ? Server::STATUS_SUSPENDED : null,
        ]);

        try {
            // Tell wings to re-sync the server state.
            $this->daemonServerRepository->setServer($server)->sync();
        } catch (\Exception $exception) {
            // Rollback the server's suspension status if wings fails to sync the server.
            $server->update([
                'status' => $isSuspending ? null : Server::STATUS_SUSPENDED,
            ]);
            throw $exception;
        }

        // Split servers share their root's billing lifecycle and have no subscription of
        // their own, so every child in the family must mirror the root's suspension state.
        $this->cascadeToSplitFamily($server, $action);
    }

    /**
     * Mirror a root server's suspension state onto every split server in its family.
     *
     * Split children always carry the top-level root id in `split_root_server_id`, so a
     * single flat query reaches the whole descendant tree (this is a no-op when $server is
     * itself a child). Best-effort: a child that is mid-transfer or fails to sync with
     * Wings is logged and skipped rather than aborting the parent operation.
     */
    private function cascadeToSplitFamily(Server $server, string $action): void
    {
        $isSuspending = $action === self::ACTION_SUSPEND;

        $children = Server::query()
            ->where('split_root_server_id', $server->id)
            ->get();

        foreach ($children as $child) {
            if (!is_null($child->transfer)) {
                Log::warning('Skipped mirroring suspension state onto a split child that is being transferred.', [
                    'root_server_id' => $server->id,
                    'child_server_id' => $child->id,
                    'action' => $action,
                ]);

                continue;
            }

            if ($isSuspending === $child->isSuspended()) {
                // The child's DB status already matches, but Wings can still have
                // drifted (the same class of bug the root-level no-op branch above
                // reconciles) - without this, a child stuck suspended in Wings while
                // its DB row already says unsuspended would never get fixed, since
                // every future toggle on the root is a no-op for this child too.
                try {
                    $this->daemonServerRepository->setServer($child)->sync();
                } catch (\Exception $exception) {
                    Log::warning('Failed to reconcile Wings suspension state for a split child during no-op mirror.', [
                        'root_server_id' => $server->id,
                        'child_server_id' => $child->id,
                        'action' => $action,
                        'error' => $exception->getMessage(),
                    ]);
                }

                continue;
            }

            $child->update([
                'status' => $isSuspending ? Server::STATUS_SUSPENDED : null,
            ]);

            try {
                $this->daemonServerRepository->setServer($child)->sync();
            } catch (\Exception $exception) {
                // Roll the child back and keep going; the parent state has already applied.
                $child->update([
                    'status' => $isSuspending ? null : Server::STATUS_SUSPENDED,
                ]);

                Log::warning('Failed to mirror suspension state onto a split child server.', [
                    'root_server_id' => $server->id,
                    'child_server_id' => $child->id,
                    'action' => $action,
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }
}
