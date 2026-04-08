<?php

namespace Pterodactyl\Services\Billing;

use Carbon\CarbonImmutable;
use Pterodactyl\Models\BillingOrder;
use Pterodactyl\Models\BillingInvoice;
use Pterodactyl\Models\BillingSubscription;
use Pterodactyl\Models\BillingSubscriptionRevision;

class BillingSubscriptionRevisionService
{
    public function latest(BillingSubscription $subscription): ?BillingSubscriptionRevision
    {
        return $subscription->revisions()->latest('id')->first();
    }

    public function record(
        BillingSubscription $subscription,
        string $type,
        ?BillingInvoice $invoice = null,
        ?BillingOrder $order = null,
        ?string $stripePriceId = null,
        ?array $stripePriceSnapshot = null
    ): BillingSubscriptionRevision {
        return BillingSubscriptionRevision::query()->create([
            'subscription_id' => $subscription->id,
            'source_invoice_id' => $invoice?->id,
            'source_order_id' => $order?->id,
            'revision_type' => $type,
            'cpu_cores' => $subscription->cpu_cores,
            'memory_gb' => $subscription->memory_gb,
            'disk_gb' => $subscription->disk_gb,
            'recurring_total' => $subscription->recurring_total,
            'stripe_price_id' => $stripePriceId ?: $subscription->provider_price_id,
            'stripe_price_snapshot' => $stripePriceSnapshot,
            'previous_revision_id' => $this->latest($subscription)?->id,
            'applied_at' => CarbonImmutable::now(),
        ]);
    }
}
