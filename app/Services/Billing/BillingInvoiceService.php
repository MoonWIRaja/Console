<?php

namespace Pterodactyl\Services\Billing;

use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Pterodactyl\Models\User;
use Pterodactyl\Models\BillingOrder;
use Pterodactyl\Models\BillingProfile;
use Pterodactyl\Models\BillingInvoice;
use Pterodactyl\Models\BillingPayment;
use Pterodactyl\Models\BillingRefund;
use Pterodactyl\Models\BillingInvoiceItem;
use Pterodactyl\Models\BillingSubscription;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Notifications\BillingInvoiceIssued;

class BillingInvoiceService
{
    public function __construct(
        private BillingCatalogService $catalogService,
        private BillingProfileService $profileService,
        private BillingTaxCalculationService $taxCalculationService,
        private BillingInvoiceNumberService $numberService,
        private BillingOrderCreationService $orderCreationService,
    ) {
    }

    public function quoteNewOrder(User $user, array $data): array
    {
        $prepared = $this->orderCreationService->prepareDraft($user, $data);
        $profile = $this->profileService->getOrCreateForUser($user);
        $snapshot = $this->profileService->snapshot($profile);
        $tax = $this->taxCalculationService->calculate(BillingInvoice::TYPE_NEW_SERVER, $snapshot, (float) $prepared['pricing']['total']);

        return [
            'type' => BillingInvoice::TYPE_NEW_SERVER,
            'currency' => config('billing.currency', 'MYR'),
            'server_name' => $prepared['server_name'],
            'resources' => [
                'cpu_cores' => $prepared['cpu_cores'],
                'memory_gb' => $prepared['memory_gb'],
                'disk_gb' => $prepared['disk_gb'],
            ],
            'subtotal' => $tax['subtotal'],
            'tax_total' => $tax['tax_total'],
            'grand_total' => $tax['grand_total'],
            'profile' => $snapshot,
            'items' => $this->buildNewOrderItems($prepared['pricing']),
            'tax_items' => $tax['items'],
        ];
    }

    public function createNewOrderInvoice(User $user, array $data): BillingOrder
    {
        return DB::transaction(function () use ($user, $data) {
            $quote = $this->quoteNewOrder($user, $data);
            $profile = $this->profileService->getOrCreateForUser($user);
            $snapshot = $this->profileService->snapshot($profile);

            $order = $this->orderCreationService->handle($user, $data, [
                'status' => BillingOrder::STATUS_AWAITING_PAYMENT,
                'order_type' => BillingOrder::TYPE_NEW_SERVER,
                'billing_profile_snapshot' => $snapshot,
            ]);

            $invoice = $this->createInvoiceRecord(
                user: $user,
                profile: $profile,
                snapshot: $snapshot,
                type: BillingInvoice::TYPE_NEW_SERVER,
                subtotal: $quote['subtotal'],
                tax: [
                    'tax_total' => $quote['tax_total'],
                    'items' => $quote['tax_items'],
                ],
                items: $quote['items'],
                order: $order,
                subscription: null,
                notes: 'Initial server order invoice.',
                dueAt: CarbonImmutable::now()->addHours((int) config('billing.invoice_due_hours', 24)),
                notifyUser: false,
            );

            $order->forceFill([
                'billing_invoice_id' => $invoice->id,
            ])->saveOrFail();

            return $order->fresh(['invoice', 'user', 'nodeConfig', 'gameProfile']);
        });
    }

    public function createRenewalInvoice(BillingSubscription $subscription, bool $notifyUser = false): BillingInvoice
    {
        $subscription->loadMissing('user', 'nodeConfig', 'lastPaidInvoice');

        if (!$subscription->hasAttachedServer()) {
            throw new DisplayException('This subscription no longer has a server attached.');
        }

        $openInvoice = $subscription->invoices()
            ->where('type', BillingInvoice::TYPE_RENEWAL)
            ->whereIn('status', [BillingInvoice::STATUS_DRAFT, BillingInvoice::STATUS_OPEN, BillingInvoice::STATUS_PROCESSING])
            ->latest('id')
            ->first();

        if ($openInvoice) {
            return $openInvoice;
        }

        return DB::transaction(function () use ($subscription, $notifyUser) {
            $profile = $this->profileService->getOrCreateForUser($subscription->user);
            $snapshot = $this->profileService->snapshot($profile);
            $subtotal = (float) $subscription->recurring_total;
            $tax = $this->taxCalculationService->calculate(BillingInvoice::TYPE_RENEWAL, $snapshot, $subtotal);

            $order = BillingOrder::query()->create([
                'user_id' => $subscription->user_id,
                'billing_node_config_id' => $subscription->billing_node_config_id,
                'billing_game_profile_id' => $subscription->billing_game_profile_id,
                'node_id' => $subscription->nodeConfig?->node_id ?? $subscription->order?->node_id,
                'egg_id' => $subscription->order?->egg_id ?? $subscription->gameProfile?->egg_id,
                'server_id' => $subscription->server_id,
                'order_type' => BillingOrder::TYPE_RENEWAL,
                'status' => BillingOrder::STATUS_AWAITING_PAYMENT,
                'billing_profile_snapshot' => $snapshot,
                'server_name' => $subscription->server_name,
                'node_name' => $subscription->node_name,
                'game_name' => $subscription->game_name,
                'cpu_cores' => $subscription->cpu_cores,
                'memory_gb' => $subscription->memory_gb,
                'disk_gb' => $subscription->disk_gb,
                'price_per_vcore' => $subscription->price_per_vcore,
                'price_per_gb_ram' => $subscription->price_per_gb_ram,
                'price_per_10gb_disk' => $subscription->price_per_10gb_disk,
                'cpu_total' => round($subscription->cpu_cores * (float) $subscription->price_per_vcore, 2),
                'memory_total' => round($subscription->memory_gb * (float) $subscription->price_per_gb_ram, 2),
                'disk_total' => round(ceil($subscription->disk_gb / BillingCatalogService::DISK_STEP_GB) * (float) $subscription->price_per_10gb_disk, 2),
                'total' => $subtotal,
                'billing_invoice_id' => null,
            ]);

            $invoice = $this->createInvoiceRecord(
                user: $subscription->user,
                profile: $profile,
                snapshot: $snapshot,
                type: BillingInvoice::TYPE_RENEWAL,
                subtotal: $tax['subtotal'],
                tax: $tax,
                items: [[
                    'type' => BillingInvoiceItem::TYPE_BASE_PLAN,
                    'description' => sprintf('Renewal for %s', $subscription->server_name),
                    'quantity' => max($subscription->renewal_period_months, 1),
                    'unit_amount' => round($subtotal / max($subscription->renewal_period_months, 1), 2),
                    'line_subtotal' => round($subtotal, 2),
                    'meta' => [
                        'subscription_id' => $subscription->id,
                        'period_months' => $subscription->renewal_period_months,
                    ],
                ]],
                order: $order,
                subscription: $subscription,
                notes: 'Subscription renewal invoice.',
                dueAt: $subscription->renews_at
                    ? CarbonImmutable::instance($subscription->renews_at)
                    : CarbonImmutable::now()->addHours((int) config('billing.invoice_due_hours', 24)),
                notifyUser: $notifyUser,
            );

            $order->forceFill(['billing_invoice_id' => $invoice->id])->saveOrFail();

            return $invoice->fresh(['items', 'order', 'subscription']);
        });
    }

    public function quoteUpgrade(BillingSubscription $subscription, array $data): array
    {
        $subscription->loadMissing('nodeConfig', 'server');

        if ($subscription->status !== BillingSubscription::STATUS_ACTIVE) {
            throw new DisplayException('Only active subscriptions can be upgraded.');
        }

        if (!$subscription->server) {
            throw new DisplayException('This subscription no longer has a linked server.');
        }

        $cpuCores = (int) $data['cpu_cores'];
        $memoryGb = (int) $data['memory_gb'];
        $diskGb = (int) $data['disk_gb'];

        if ($cpuCores < $subscription->cpu_cores || $memoryGb < $subscription->memory_gb || $diskGb < $subscription->disk_gb) {
            throw new DisplayException('Downgrades are not allowed through the upgrade billing flow.');
        }

        $limits = $this->catalogService->getSubscriptionUpgradeLimits($subscription);

        if ($cpuCores < 1 || $cpuCores > $limits['max_cpu']) {
            throw new DisplayException(sprintf('vCore must stay between 1 and %d.', $limits['max_cpu']));
        }

        if ($memoryGb < 1 || $memoryGb > $limits['max_memory_gb']) {
            throw new DisplayException(sprintf('RAM must stay between 1 GB and %d GB.', $limits['max_memory_gb']));
        }

        if ($diskGb < BillingCatalogService::DISK_STEP_GB || $diskGb > $limits['max_disk_gb']) {
            throw new DisplayException(sprintf(
                'Storage must stay between %d GB and %d GB.',
                BillingCatalogService::DISK_STEP_GB,
                $limits['max_disk_gb']
            ));
        }

        if ($diskGb % BillingCatalogService::DISK_STEP_GB !== 0) {
            throw new DisplayException(sprintf('Storage must use %d GB steps.', BillingCatalogService::DISK_STEP_GB));
        }

        $newPricing = $subscription->nodeConfig
            ? $this->catalogService->calculatePricing($subscription->nodeConfig, $cpuCores, $memoryGb, $diskGb)
            : ['total' => (float) $subscription->recurring_total];

        $currentMonthly = round((float) $subscription->recurring_total, 2);
        $newMonthly = round((float) $newPricing['total'], 2);
        $periodMonths = max((int) $subscription->renewal_period_months, 1);
        $now = CarbonImmutable::now();
        $cycleEnd = $subscription->renews_at ? CarbonImmutable::instance($subscription->renews_at) : $now;
        $cycleStart = $cycleEnd->subMonthsNoOverflow($periodMonths);
        $remainingCycleSeconds = $cycleEnd->isFuture()
            ? max($now->diffInSeconds($cycleEnd, false), 0)
            : 0;
        $totalCycleSeconds = max($cycleStart->diffInSeconds($cycleEnd, false), 1);
        $proratedAmount = round(max(($newMonthly - $currentMonthly) * ($remainingCycleSeconds / $totalCycleSeconds), 0), 2);

        $profile = $this->profileService->getOrCreateForUser($subscription->user);
        $snapshot = $this->profileService->snapshot($profile);
        $tax = $this->taxCalculationService->calculate(BillingInvoice::TYPE_UPGRADE, $snapshot, $proratedAmount);

        return [
            'type' => BillingInvoice::TYPE_UPGRADE,
            'subscription_id' => $subscription->id,
            'current' => [
                'cpu_cores' => $subscription->cpu_cores,
                'memory_gb' => $subscription->memory_gb,
                'disk_gb' => $subscription->disk_gb,
                'monthly_total' => $currentMonthly,
            ],
            'target' => [
                'cpu_cores' => $cpuCores,
                'memory_gb' => $memoryGb,
                'disk_gb' => $diskGb,
                'monthly_total' => $newMonthly,
            ],
            'remaining_cycle_seconds' => $remainingCycleSeconds,
            'total_cycle_seconds' => $totalCycleSeconds,
            'subtotal' => $tax['subtotal'],
            'tax_total' => $tax['tax_total'],
            'grand_total' => $tax['grand_total'],
            'profile' => $snapshot,
            'items' => [[
                'type' => BillingInvoiceItem::TYPE_UPGRADE_PRORATION,
                'description' => sprintf('Prorated upgrade for %s', $subscription->server_name),
                'quantity' => 1,
                'unit_amount' => $proratedAmount,
                'line_subtotal' => $proratedAmount,
                'meta' => [
                    'subscription_id' => $subscription->id,
                    'current_monthly_total' => $currentMonthly,
                    'new_monthly_total' => $newMonthly,
                ],
            ]],
            'tax_items' => $tax['items'],
        ];
    }

    public function createUpgradeInvoice(BillingSubscription $subscription, array $data): BillingInvoice
    {
        return DB::transaction(function () use ($subscription, $data) {
            $quote = $this->quoteUpgrade($subscription, $data);
            $profile = $this->profileService->getOrCreateForUser($subscription->user);
            $snapshot = $this->profileService->snapshot($profile);

            $order = BillingOrder::query()->create([
                'user_id' => $subscription->user_id,
                'billing_node_config_id' => $subscription->billing_node_config_id,
                'billing_game_profile_id' => $subscription->billing_game_profile_id,
                'node_id' => $subscription->nodeConfig?->node_id ?? $subscription->order?->node_id,
                'egg_id' => $subscription->order?->egg_id ?? $subscription->gameProfile?->egg_id,
                'server_id' => $subscription->server_id,
                'order_type' => BillingOrder::TYPE_UPGRADE,
                'status' => BillingOrder::STATUS_AWAITING_PAYMENT,
                'billing_profile_snapshot' => $snapshot,
                'server_name' => $subscription->server_name,
                'node_name' => $subscription->node_name,
                'game_name' => $subscription->game_name,
                'cpu_cores' => $quote['target']['cpu_cores'],
                'memory_gb' => $quote['target']['memory_gb'],
                'disk_gb' => $quote['target']['disk_gb'],
                'price_per_vcore' => $subscription->nodeConfig?->price_per_vcore ?? $subscription->price_per_vcore,
                'price_per_gb_ram' => $subscription->nodeConfig?->price_per_gb_ram ?? $subscription->price_per_gb_ram,
                'price_per_10gb_disk' => $subscription->nodeConfig?->price_per_10gb_disk ?? $subscription->price_per_10gb_disk,
                'cpu_total' => round($quote['target']['cpu_cores'] * (float) ($subscription->nodeConfig?->price_per_vcore ?? $subscription->price_per_vcore), 2),
                'memory_total' => round($quote['target']['memory_gb'] * (float) ($subscription->nodeConfig?->price_per_gb_ram ?? $subscription->price_per_gb_ram), 2),
                'disk_total' => round(ceil($quote['target']['disk_gb'] / BillingCatalogService::DISK_STEP_GB) * (float) ($subscription->nodeConfig?->price_per_10gb_disk ?? $subscription->price_per_10gb_disk), 2),
                'total' => $quote['subtotal'],
                'billing_invoice_id' => null,
            ]);

            $invoice = $this->createInvoiceRecord(
                user: $subscription->user,
                profile: $profile,
                snapshot: $snapshot,
                type: BillingInvoice::TYPE_UPGRADE,
                subtotal: $quote['subtotal'],
                tax: [
                    'tax_total' => $quote['tax_total'],
                    'items' => $quote['tax_items'],
                ],
                items: $quote['items'],
                order: $order,
                subscription: $subscription,
                notes: 'Subscription upgrade invoice.',
                dueAt: CarbonImmutable::now()->addHours((int) config('billing.invoice_due_hours', 24)),
                notifyUser: false,
            );

            $order->forceFill(['billing_invoice_id' => $invoice->id])->saveOrFail();

            return $invoice->fresh(['items', 'order', 'subscription']);
        });
    }

    public function markPaid(BillingInvoice $invoice, BillingPayment $payment): BillingInvoice
    {
        return DB::transaction(function () use ($invoice, $payment) {
            $invoice->forceFill([
                'status' => BillingInvoice::STATUS_PAID,
                'paid_at' => $payment->paid_at ?? CarbonImmutable::now(),
            ])->saveOrFail();

            if ($invoice->order) {
                $invoice->order->forceFill([
                    'status' => BillingOrder::STATUS_PAID,
                    'payment_verified_at' => $invoice->paid_at,
                ])->saveOrFail();
            }

            if ($invoice->subscription) {
                $invoice->subscription->forceFill([
                    'last_paid_invoice_id' => $invoice->id,
                    'failed_payment_count' => 0,
                    'status' => in_array($invoice->type, [BillingInvoice::TYPE_RENEWAL, BillingInvoice::TYPE_UPGRADE], true)
                        ? BillingSubscription::STATUS_ACTIVE
                        : $invoice->subscription->status,
                    'renews_at' => $invoice->type === BillingInvoice::TYPE_RENEWAL
                        ? ($invoice->subscription->renews_at && $invoice->subscription->renews_at->isFuture()
                            ? $invoice->subscription->renews_at->copy()->addMonthsNoOverflow(max($invoice->subscription->renewal_period_months, 1))
                            : CarbonImmutable::now()->addMonthsNoOverflow(max($invoice->subscription->renewal_period_months, 1)))
                        : $invoice->subscription->renews_at,
                    'renewal_reminder_sent_at' => null,
                    'renewed_at' => $invoice->type === BillingInvoice::TYPE_RENEWAL ? CarbonImmutable::now() : $invoice->subscription->renewed_at,
                    'upgraded_at' => $invoice->type === BillingInvoice::TYPE_UPGRADE ? CarbonImmutable::now() : $invoice->subscription->upgraded_at,
                    'suspended_at' => null,
                    'grace_suspend_at' => null,
                    'grace_delete_at' => null,
                    'deletion_scheduled_at' => null,
                ])->saveOrFail();
            }

            return $invoice->fresh(['order', 'subscription']);
        });
    }

    public function markFailed(BillingInvoice $invoice, ?string $notes = null): BillingInvoice
    {
        $invoice->forceFill([
            'status' => BillingInvoice::STATUS_FAILED,
            'notes' => trim(implode("\n\n", array_filter([$invoice->notes, $notes]))),
        ])->saveOrFail();

        return $invoice->fresh(['order']);
    }

    public function markExpired(BillingInvoice $invoice, ?string $notes = null): BillingInvoice
    {
        return DB::transaction(function () use ($invoice, $notes) {
            $invoice->forceFill([
                'status' => BillingInvoice::STATUS_EXPIRED,
                'notes' => trim(implode("\n\n", array_filter([$invoice->notes, $notes]))),
            ])->saveOrFail();

            if ($invoice->order && in_array($invoice->order->status, BillingOrder::ACTIVE_RESERVATION_STATUSES, true)) {
                $invoice->order->forceFill([
                    'status' => BillingOrder::STATUS_CANCELLED,
                    'admin_notes' => trim(implode("\n\n", array_filter([
                        $invoice->order->admin_notes,
                        'Order reservation was released because the linked invoice expired before payment completed.',
                    ]))),
                ])->saveOrFail();
            }

            return $invoice->fresh(['order']);
        });
    }

    public function applyRefundStatus(BillingInvoice $invoice): BillingInvoice
    {
        $refundTotal = round((float) $invoice->payments()
            ->with('refunds')
            ->get()
            ->flatMap(fn (BillingPayment $payment) => $payment->refunds->where('status', BillingRefund::STATUS_COMPLETED))
            ->sum('amount'), 2);

        if ($refundTotal <= 0) {
            if (in_array($invoice->status, [
                BillingInvoice::STATUS_REFUNDED,
                BillingInvoice::STATUS_PARTIALLY_REFUNDED,
            ], true)) {
                $hasSuccessfulPayment = $invoice->payments()
                    ->whereIn('status', [
                        BillingPayment::STATUS_VERIFIED_PAID,
                        BillingPayment::STATUS_REFUND_PENDING,
                        BillingPayment::STATUS_REFUNDED,
                    ])
                    ->exists();

                $invoice->forceFill([
                    'status' => $hasSuccessfulPayment
                        ? BillingInvoice::STATUS_PAID
                        : BillingInvoice::STATUS_OPEN,
                ])->saveOrFail();
            }

            return $invoice->fresh();
        }

        $status = $refundTotal >= (float) $invoice->grand_total
            ? BillingInvoice::STATUS_REFUNDED
            : BillingInvoice::STATUS_PARTIALLY_REFUNDED;

        $invoice->forceFill(['status' => $status])->saveOrFail();

        if ($invoice->order && $status === BillingInvoice::STATUS_REFUNDED) {
            $invoice->order->forceFill(['status' => BillingOrder::STATUS_REFUNDED])->saveOrFail();
        }

        return $invoice->fresh(['order']);
    }

    private function createInvoiceRecord(
        User $user,
        BillingProfile $profile,
        array $snapshot,
        string $type,
        float $subtotal,
        array $tax,
        array $items,
        ?BillingOrder $order = null,
        ?BillingSubscription $subscription = null,
        ?string $notes = null,
        ?CarbonImmutable $dueAt = null,
        bool $notifyUser = false,
    ): BillingInvoice {
        $invoice = BillingInvoice::query()->create([
            'invoice_number' => $this->numberService->nextInvoiceNumber(),
            'user_id' => $user->id,
            'billing_profile_id' => $profile->id,
            'billing_order_id' => $order?->id,
            'subscription_id' => $subscription?->id,
            'type' => $type,
            'currency' => config('billing.currency', 'MYR'),
            'subtotal' => round($subtotal, 2),
            'tax_total' => round((float) ($tax['tax_total'] ?? 0), 2),
            'grand_total' => round($subtotal + (float) ($tax['tax_total'] ?? 0), 2),
            'status' => BillingInvoice::STATUS_OPEN,
            'issued_at' => CarbonImmutable::now(),
            'due_at' => $dueAt,
            'billing_profile_snapshot' => $snapshot,
            'notes' => $notes,
        ]);

        foreach ($items as $item) {
            $invoice->items()->create([
                'type' => $item['type'],
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit_amount' => round((float) $item['unit_amount'], 2),
                'line_subtotal' => round((float) $item['line_subtotal'], 2),
                'meta' => Arr::wrap($item['meta'] ?? []),
            ]);
        }

        foreach ($tax['items'] ?? [] as $taxItem) {
            $invoice->items()->create([
                'type' => BillingInvoiceItem::TYPE_TAX,
                'description' => $taxItem['name'],
                'quantity' => 1,
                'unit_amount' => round((float) $taxItem['amount'], 2),
                'line_subtotal' => round((float) $taxItem['amount'], 2),
                'meta' => [
                    'rule_id' => $taxItem['rule_id'] ?? null,
                    'rate_type' => $taxItem['rate_type'] ?? null,
                    'rate_value' => $taxItem['rate_value'] ?? null,
                ],
            ]);
        }

        if ($notifyUser) {
            $user->notify(new BillingInvoiceIssued($invoice->fresh(['items', 'order', 'subscription'])));
        }

        return $invoice;
    }

    private function buildNewOrderItems(array $pricing): array
    {
        return [
            [
                'type' => BillingInvoiceItem::TYPE_BASE_PLAN,
                'description' => 'CPU resources',
                'quantity' => 1,
                'unit_amount' => round((float) $pricing['cpu_total'], 2),
                'line_subtotal' => round((float) $pricing['cpu_total'], 2),
                'meta' => ['component' => 'cpu'],
            ],
            [
                'type' => BillingInvoiceItem::TYPE_BASE_PLAN,
                'description' => 'Memory resources',
                'quantity' => 1,
                'unit_amount' => round((float) $pricing['memory_total'], 2),
                'line_subtotal' => round((float) $pricing['memory_total'], 2),
                'meta' => ['component' => 'memory'],
            ],
            [
                'type' => BillingInvoiceItem::TYPE_BASE_PLAN,
                'description' => 'Disk resources',
                'quantity' => 1,
                'unit_amount' => round((float) $pricing['disk_total'], 2),
                'line_subtotal' => round((float) $pricing['disk_total'], 2),
                'meta' => ['component' => 'disk'],
            ],
        ];
    }
}
