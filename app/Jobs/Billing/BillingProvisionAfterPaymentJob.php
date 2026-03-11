<?php

namespace Pterodactyl\Jobs\Billing;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Pterodactyl\Models\BillingOrder;
use Pterodactyl\Services\Billing\BillingOrderProvisionService;

class BillingProvisionAfterPaymentJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(private int $orderId)
    {
    }

    public function handle(BillingOrderProvisionService $provisionService): void
    {
        /** @var BillingOrder|null $order */
        $order = BillingOrder::query()->find($this->orderId);
        if (!$order) {
            return;
        }

        $provisionService->handle($order);
    }
}
