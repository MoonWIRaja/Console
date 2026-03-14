<?php

namespace Pterodactyl\Services\Billing;

use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\BillingOrder;
use Pterodactyl\Models\BillingInvoice;
use Pterodactyl\Models\BillingSubscription;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Services\Servers\SuspensionService;
use Pterodactyl\Services\Servers\BuildModificationService;

class BillingSubscriptionService
{
    public function __construct(
        private BillingCatalogService $catalogService,
        private SuspensionService $suspensionService,
        private BuildModificationService $buildModificationService,
        private StripeClientFactory $stripe,
        private BillingSubscriptionRevisionService $revisionService,
    ) {
    }

    public function createFromProvisionedOrder(BillingOrder $order): BillingSubscription
    {
        $order->loadMissing('invoice.payments');

        $now = CarbonImmutable::now();
        $existing = BillingSubscription::query()->where('billing_order_id', $order->id)->first();
        $gatewayProvider = $existing?->gateway_provider ?: ($order->invoice?->provider ?: config('billing.gateway.default', 'fiuu'));
        $renewsAt = $existing?->provider_current_period_end
            ?: $existing?->renews_at
            ?: $now->addMonthsNoOverflow(1);
        $gatewayReferences = $this->extractGatewayReferencesFromOrder($order);

        $subscription = BillingSubscription::query()->updateOrCreate(
            ['billing_order_id' => $order->id],
            [
                'user_id' => $order->user_id,
                'server_id' => $order->server_id,
                'billing_node_config_id' => $order->billing_node_config_id,
                'billing_game_profile_id' => $order->billing_game_profile_id,
                'status' => BillingSubscription::STATUS_ACTIVE,
                'auto_renew' => $gatewayProvider === StripeCheckoutService::PROVIDER
                    ? ($existing?->auto_renew ?? true)
                    : false,
                'gateway_provider' => $gatewayProvider,
                'gateway_customer_reference' => $existing?->gateway_customer_reference ?: $gatewayReferences['customer_reference'],
                'gateway_token_reference' => $gatewayProvider === StripeCheckoutService::PROVIDER
                    ? null
                    : ($existing?->gateway_token_reference ?: $gatewayReferences['token_reference']),
                'provider_subscription_id' => $existing?->provider_subscription_id,
                'provider_subscription_item_id' => $existing?->provider_subscription_item_id,
                'provider_price_id' => $existing?->provider_price_id,
                'provider_status' => $existing?->provider_status,
                'provider_current_period_start' => $existing?->provider_current_period_start,
                'provider_current_period_end' => $existing?->provider_current_period_end,
                'provider_cancel_at' => $existing?->provider_cancel_at,
                'migration_source' => $existing?->migration_source,
                'migration_state' => $existing?->migration_state,
                'server_name' => $order->server_name,
                'node_name' => $order->node_name,
                'game_name' => $order->game_name,
                'cpu_cores' => $order->cpu_cores,
                'memory_gb' => $order->memory_gb,
                'disk_gb' => $order->disk_gb,
                'price_per_vcore' => $order->price_per_vcore,
                'price_per_gb_ram' => $order->price_per_gb_ram,
                'price_per_10gb_disk' => $order->price_per_10gb_disk,
                'recurring_total' => $order->total,
                'renewal_period_months' => 1,
                'renews_at' => $renewsAt,
                'next_invoice_at' => $renewsAt->subDays((int) config('billing.invoice_lead_days', 7)),
                'grace_suspend_at' => null,
                'grace_delete_at' => null,
                'last_paid_invoice_id' => $order->billing_invoice_id,
                'failed_payment_count' => 0,
                'renewal_reminder_sent_at' => null,
                'renewed_at' => null,
                'upgraded_at' => null,
                'suspended_at' => null,
                'deletion_scheduled_at' => null,
                'deleted_at' => null,
            ]
        );

        if (!$subscription->revisions()->where('source_order_id', $order->id)->exists()) {
            $this->revisionService->record(
                $subscription,
                'new_server',
                $order->invoice,
                $order,
                $subscription->provider_price_id
            );
        }

        return $subscription;
    }

    public function renew(BillingSubscription $subscription): BillingSubscription
    {
        $subscription->loadMissing('nodeConfig', 'server');

        if ($subscription->status === BillingSubscription::STATUS_DELETED) {
            throw new DisplayException('This billing subscription has already been deleted and cannot be renewed.');
        }

        if (!$subscription->hasAttachedServer() || !$subscription->server) {
            throw new DisplayException('This billing subscription no longer has a server attached to it.');
        }

        if (!$subscription->isRenewWindowOpen()) {
            throw new DisplayException(sprintf(
                'This subscription can only be renewed within %d days of the billing deadline.',
                BillingSubscription::RENEWAL_WINDOW_DAYS
            ));
        }

        $pricing = $this->catalogService->calculatePricing(
            $subscription->nodeConfig,
            $subscription->cpu_cores,
            $subscription->memory_gb,
            $subscription->disk_gb
        );

        $base = $subscription->renews_at && $subscription->renews_at->isFuture()
            ? CarbonImmutable::instance($subscription->renews_at)
            : CarbonImmutable::now();

        $subscription->forceFill([
            'status' => BillingSubscription::STATUS_ACTIVE,
            'price_per_vcore' => $subscription->nodeConfig->price_per_vcore,
            'price_per_gb_ram' => $subscription->nodeConfig->price_per_gb_ram,
            'price_per_10gb_disk' => $subscription->nodeConfig->price_per_10gb_disk,
            'recurring_total' => $pricing['total'],
            'renews_at' => $base->addMonthsNoOverflow(max($subscription->renewal_period_months, 1)),
            'renewal_reminder_sent_at' => null,
            'renewed_at' => CarbonImmutable::now(),
            'suspended_at' => null,
            'deletion_scheduled_at' => null,
        ])->saveOrFail();

        if ($subscription->server && $subscription->server->isSuspended()) {
            $this->suspensionService->toggle($subscription->server, SuspensionService::ACTION_UNSUSPEND);
        }

        return $subscription->fresh(['server', 'nodeConfig']);
    }

    public function upgrade(BillingSubscription $subscription, array $data): BillingSubscription
    {
        $subscription->loadMissing('nodeConfig.node', 'gameProfile.egg.nest', 'server');

        if ($subscription->status !== BillingSubscription::STATUS_ACTIVE) {
            throw new DisplayException('Only active billing subscriptions can be upgraded.');
        }

        if (!$subscription->server) {
            throw new DisplayException('This billing subscription no longer has a server attached to it.');
        }

        $cpuCores = (int) $data['cpu_cores'];
        $memoryGb = (int) $data['memory_gb'];
        $diskGb = (int) $data['disk_gb'];

        if ($cpuCores < $subscription->cpu_cores || $memoryGb < $subscription->memory_gb || $diskGb < $subscription->disk_gb) {
            throw new DisplayException('Downgrades are not allowed. Only equal or higher resource values can be submitted.');
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

        /** @var Server $server */
        $server = $subscription->server;
        $this->buildModificationService->handle($server, [
            'memory' => $memoryGb * 1024,
            'swap' => $server->swap,
            'io' => $server->io,
            'cpu' => $cpuCores * 100,
            'disk' => $diskGb * 1024,
            'allocation_id' => $server->allocation_id,
            'oom_disabled' => $server->oom_disabled,
            'allocation_limit' => $server->allocation_limit,
            'database_limit' => $server->database_limit,
            'backup_limit' => $server->backup_limit,
        ]);

        $pricing = $this->catalogService->calculatePricing($subscription->nodeConfig, $cpuCores, $memoryGb, $diskGb);

        $subscription->forceFill([
            'cpu_cores' => $cpuCores,
            'memory_gb' => $memoryGb,
            'disk_gb' => $diskGb,
            'price_per_vcore' => $subscription->nodeConfig->price_per_vcore,
            'price_per_gb_ram' => $subscription->nodeConfig->price_per_gb_ram,
            'price_per_10gb_disk' => $subscription->nodeConfig->price_per_10gb_disk,
            'recurring_total' => $pricing['total'],
            'upgraded_at' => CarbonImmutable::now(),
        ])->saveOrFail();

        return $subscription->fresh(['server', 'nodeConfig', 'gameProfile.egg.nest']);
    }

    public function toggleAutoRenew(BillingSubscription $subscription, bool $enabled): BillingSubscription
    {
        if ($subscription->gateway_provider === StripeCheckoutService::PROVIDER || filled($subscription->provider_subscription_id)) {
            if (blank($subscription->provider_subscription_id)) {
                throw new DisplayException('This Stripe subscription has not been linked yet.');
            }

            $stripeSubscription = $this->stripe->make()->subscriptions->update($subscription->provider_subscription_id, [
                'cancel_at_period_end' => !$enabled,
            ])->toArray();

            $subscription->forceFill([
                'auto_renew' => !(bool) Arr::get($stripeSubscription, 'cancel_at_period_end', false),
                'provider_status' => Arr::get($stripeSubscription, 'status', $subscription->provider_status),
                'provider_cancel_at' => $this->mapTimestamp(Arr::get($stripeSubscription, 'cancel_at')),
                'provider_current_period_start' => $this->mapTimestamp(Arr::get($stripeSubscription, 'current_period_start')),
                'provider_current_period_end' => $this->mapTimestamp(Arr::get($stripeSubscription, 'current_period_end')),
            ])->saveOrFail();

            return $subscription->fresh();
        }

        if ($enabled && blank($subscription->gateway_token_reference)) {
            throw new DisplayException('Auto-renew needs a stored recurring payment token. Complete a supported tokenized card payment first, then enable auto-renew.');
        }

        $subscription->forceFill([
            'auto_renew' => $enabled,
        ])->saveOrFail();

        return $subscription->fresh();
    }

    public function applyPaidRenewal(BillingSubscription $subscription): BillingSubscription
    {
        $subscription->loadMissing('server');

        if ($subscription->server && $subscription->server->isSuspended()) {
            $this->suspensionService->toggle($subscription->server, SuspensionService::ACTION_UNSUSPEND);
        }

        $subscription->forceFill([
            'status' => BillingSubscription::STATUS_ACTIVE,
            'suspended_at' => null,
            'deletion_scheduled_at' => null,
            'grace_suspend_at' => null,
            'grace_delete_at' => null,
            'next_invoice_at' => $subscription->renews_at
                ? CarbonImmutable::instance($subscription->renews_at)->subDays((int) config('billing.invoice_lead_days', 7))
                : null,
        ])->saveOrFail();

        return $subscription->fresh(['server', 'nodeConfig']);
    }

    public function applyPaidUpgrade(BillingSubscription $subscription, BillingOrder $order): BillingSubscription
    {
        $subscription->loadMissing('nodeConfig.node', 'gameProfile.egg.nest', 'server');

        if (!$subscription->server) {
            throw new DisplayException('This billing subscription no longer has a server attached to it.');
        }

        /** @var Server $server */
        $server = $subscription->server;
        $this->buildModificationService->handle($server, [
            'memory' => $order->memory_gb * 1024,
            'swap' => $server->swap,
            'io' => $server->io,
            'cpu' => $order->cpu_cores * 100,
            'disk' => $order->disk_gb * 1024,
            'allocation_id' => $server->allocation_id,
            'oom_disabled' => $server->oom_disabled,
            'allocation_limit' => $server->allocation_limit,
            'database_limit' => $server->database_limit,
            'backup_limit' => $server->backup_limit,
        ]);

        $pricing = $this->catalogService->calculatePricing(
            $subscription->nodeConfig,
            $order->cpu_cores,
            $order->memory_gb,
            $order->disk_gb
        );

        $subscription->forceFill([
            'cpu_cores' => $order->cpu_cores,
            'memory_gb' => $order->memory_gb,
            'disk_gb' => $order->disk_gb,
            'price_per_vcore' => $subscription->nodeConfig->price_per_vcore,
            'price_per_gb_ram' => $subscription->nodeConfig->price_per_gb_ram,
            'price_per_10gb_disk' => $subscription->nodeConfig->price_per_10gb_disk,
            'recurring_total' => $pricing['total'],
            'upgraded_at' => CarbonImmutable::now(),
        ])->saveOrFail();

        $this->revisionService->record(
            $subscription->fresh(),
            'upgrade',
            $order->invoice,
            $order,
            $subscription->provider_price_id
        );

        return $subscription->fresh(['server', 'nodeConfig', 'gameProfile.egg.nest']);
    }

    public function scheduleTerminationAfterRefund(BillingSubscription $subscription, ?CarbonImmutable $now = null): BillingSubscription
    {
        $now ??= CarbonImmutable::now();

        $suspendAt = $subscription->grace_suspend_at
            ? CarbonImmutable::instance($subscription->grace_suspend_at)
            : $now->addHours((int) config('billing.refund_suspend_hours', 5));
        $deleteAt = $subscription->grace_delete_at
            ? CarbonImmutable::instance($subscription->grace_delete_at)
            : $suspendAt->addHours((int) config('billing.refund_delete_after_suspend_hours', 24));

        $subscription->forceFill([
            'status' => BillingSubscription::STATUS_CANCELLED,
            'auto_renew' => false,
            'next_invoice_at' => null,
            'grace_suspend_at' => $suspendAt,
            'grace_delete_at' => $deleteAt,
            'deletion_scheduled_at' => $deleteAt,
        ])->saveOrFail();

        return $subscription->fresh(['server', 'nodeConfig', 'gameProfile.egg.nest']);
    }

    public function revertLatestUpgradeAfterRefund(BillingSubscription $subscription): BillingSubscription
    {
        $subscription->loadMissing('server');

        if (!$subscription->server) {
            throw new DisplayException('This billing subscription no longer has a server attached to it.');
        }

        $effectiveOrder = $this->resolveLatestEffectiveResourceOrder($subscription);
        if (!$effectiveOrder) {
            throw new DisplayException('No previous paid resource state could be found for this server.');
        }

        if (
            $subscription->cpu_cores === $effectiveOrder->cpu_cores
            && $subscription->memory_gb === $effectiveOrder->memory_gb
            && $subscription->disk_gb === $effectiveOrder->disk_gb
            && round((float) $subscription->recurring_total, 2) === round((float) $effectiveOrder->total, 2)
        ) {
            return $subscription->fresh(['server', 'nodeConfig', 'gameProfile.egg.nest']);
        }

        /** @var Server $server */
        $server = $subscription->server;
        $this->buildModificationService->handle($server, [
            'memory' => $effectiveOrder->memory_gb * 1024,
            'swap' => $server->swap,
            'io' => $server->io,
            'cpu' => $effectiveOrder->cpu_cores * 100,
            'disk' => $effectiveOrder->disk_gb * 1024,
            'allocation_id' => $server->allocation_id,
            'oom_disabled' => $server->oom_disabled,
            'allocation_limit' => $server->allocation_limit,
            'database_limit' => $server->database_limit,
            'backup_limit' => $server->backup_limit,
        ]);

        $subscription->forceFill([
            'cpu_cores' => $effectiveOrder->cpu_cores,
            'memory_gb' => $effectiveOrder->memory_gb,
            'disk_gb' => $effectiveOrder->disk_gb,
            'price_per_vcore' => $effectiveOrder->price_per_vcore,
            'price_per_gb_ram' => $effectiveOrder->price_per_gb_ram,
            'price_per_10gb_disk' => $effectiveOrder->price_per_10gb_disk,
            'recurring_total' => $effectiveOrder->total,
            'last_paid_invoice_id' => $effectiveOrder->billing_invoice_id,
            'upgraded_at' => CarbonImmutable::now(),
        ])->saveOrFail();

        $this->revisionService->record(
            $subscription->fresh(),
            'refund_rollback',
            $effectiveOrder->invoice,
            $effectiveOrder,
            $subscription->provider_price_id
        );

        return $subscription->fresh(['server', 'nodeConfig', 'gameProfile.egg.nest']);
    }

    private function extractGatewayReferencesFromOrder(BillingOrder $order): array
    {
        $raw = $order->invoice?->payments?->sortByDesc('id')->first()?->raw_gateway_response ?? [];
        $callback = is_array($raw['callback'] ?? null) ? $raw['callback'] : [];
        $requery = is_array($raw['requery'] ?? null) ? $raw['requery'] : [];

        return [
            'token_reference' => $callback['token_reference']
                ?? $callback['token']
                ?? $callback['token_id']
                ?? $callback['cc_token']
                ?? $requery['token_reference']
                ?? $requery['token']
                ?? $requery['token_id']
                ?? null,
            'customer_reference' => $callback['customer_reference']
                ?? $callback['customer_id']
                ?? $callback['cust_ref']
                ?? $raw['customer']
                ?? $requery['customer_reference']
                ?? $requery['customer_id']
                ?? $requery['cust_ref']
                ?? null,
        ];
    }

    private function mapTimestamp(mixed $timestamp): ?CarbonImmutable
    {
        if (!is_numeric($timestamp)) {
            return null;
        }

        return CarbonImmutable::createFromTimestampUTC((int) $timestamp);
    }

    private function resolveLatestEffectiveResourceOrder(BillingSubscription $subscription): ?BillingOrder
    {
        return BillingOrder::query()
            ->where('user_id', $subscription->user_id)
            ->where('server_id', $subscription->server_id)
            ->whereIn('order_type', [
                BillingOrder::TYPE_NEW_SERVER,
                BillingOrder::TYPE_UPGRADE,
            ])
            ->whereHas('invoice', function ($query) {
                $query->whereIn('status', [
                    BillingInvoice::STATUS_PAID,
                    BillingInvoice::STATUS_PARTIALLY_REFUNDED,
                ]);
            })
            ->orderByDesc('payment_verified_at')
            ->orderByDesc('provisioned_at')
            ->orderByDesc('id')
            ->first();
    }
}
