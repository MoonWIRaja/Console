<?php

namespace Pterodactyl\Observers;

use Pterodactyl\Models\BillingNodeConfig;
use Pterodactyl\Services\Billing\BillingNodeSyncService;

class BillingNodeConfigObserver
{
    public function __construct(private BillingNodeSyncService $syncService)
    {
    }

    public function updated(BillingNodeConfig $config): void
    {
        if (!$config->wasChanged([
            'display_name',
            'price_per_vcore',
            'price_per_gb_ram',
            'price_per_10gb_disk',
        ])) {
            return;
        }

        $this->syncService->syncConfig($config->fresh(['node']));
    }
}
