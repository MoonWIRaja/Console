<?php

namespace Pterodactyl\Observers;

use Pterodactyl\Models\Node;
use Pterodactyl\Services\Billing\BillingNodeSyncService;

class NodeObserver
{
    public function __construct(private BillingNodeSyncService $syncService)
    {
    }

    public function updated(Node $node): void
    {
        if (!$node->wasChanged('name')) {
            return;
        }

        $this->syncService->syncNode($node);
    }
}
