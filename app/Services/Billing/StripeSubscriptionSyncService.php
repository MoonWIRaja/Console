<?php

namespace Pterodactyl\Services\Billing;

use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Pterodactyl\Models\BillingInvoice;
use Pterodactyl\Models\BillingOrder;
use Pterodactyl\Models\BillingSubscription;

class StripeSubscriptionSyncService
{
    public function ensureDraftSubscription(BillingInvoice $invoice, string $customerId, string $priceId): BillingSubscription
    {
        $invoice->loadMissing('order.user', 'order.nodeConfig', 'order.gameProfile');
        $order = $invoice->order;

        $subscription = BillingSubscription::query()->updateOrCreate(
            ['billing_order_id' => $order?->id],
            [
                'user_id' => $invoice->user_id,
                'server_id' => $order?->server_id,
                'billing_node_config_id' => $order?->billing_node_config_id,
                'billing_game_profile_id' => $order?->billing_game_profile_id,
                'status' => BillingSubscription::STATUS_PENDING_ACTIVATION,
                'auto_renew' => true,
                'gateway_provider' => 'stripe',
                'gateway_customer_reference' => $customerId,
                'provider_price_id' => $priceId,
                'provider_status' => 'pending_checkout',
                'server_name' => $order?->server_name ?? $invoice->order?->server_name ?? $invoice->invoice_number,
                'node_name' => $order?->node_name,
                'game_name' => $order?->game_name,
                'cpu_cores' => $order?->cpu_cores ?? 1,
                'memory_gb' => $order?->memory_gb ?? 1,
                'disk_gb' => $order?->disk_gb ?? 10,
                'price_per_vcore' => $order?->price_per_vcore ?? 0,
                'price_per_gb_ram' => $order?->price_per_gb_ram ?? 0,
                'price_per_10gb_disk' => $order?->price_per_10gb_disk ?? 0,
                'recurring_total' => $order?->total ?? $invoice->subtotal,
                'renewal_period_months' => 1,
                'next_invoice_at' => null,
            ]
        );

        if ((int) $invoice->subscription_id !== (int) $subscription->id) {
            $invoice->forceFill([
                'subscription_id' => $subscription->id,
            ])->saveOrFail();
        }

        return $subscription->fresh(['order']);
    }

    public function syncFromStripeSubscription(BillingSubscription $subscription, array $stripeSubscription): BillingSubscription
    {
        $status = match (Arr::get($stripeSubscription, 'status')) {
            'active', 'trialing' => $subscription->hasAttachedServer()
                ? BillingSubscription::STATUS_ACTIVE
                : BillingSubscription::STATUS_PENDING_ACTIVATION,
            'past_due', 'unpaid', 'incomplete' => BillingSubscription::STATUS_PAST_DUE,
            'canceled', 'incomplete_expired' => BillingSubscription::STATUS_CANCELLED,
            default => $subscription->status,
        };

        $subscription->forceFill([
            'gateway_provider' => 'stripe',
            'gateway_customer_reference' => Arr::get($stripeSubscription, 'customer', $subscription->gateway_customer_reference),
            'provider_subscription_id' => Arr::get($stripeSubscription, 'id', $subscription->provider_subscription_id),
            'provider_subscription_item_id' => Arr::get($stripeSubscription, 'items.data.0.id', $subscription->provider_subscription_item_id),
            'provider_price_id' => Arr::get($stripeSubscription, 'items.data.0.price.id', $subscription->provider_price_id),
            'provider_status' => Arr::get($stripeSubscription, 'status', $subscription->provider_status),
            'provider_current_period_start' => $this->mapTimestamp(Arr::get($stripeSubscription, 'current_period_start')),
            'provider_current_period_end' => $this->mapTimestamp(Arr::get($stripeSubscription, 'current_period_end')),
            'provider_cancel_at' => $this->mapTimestamp(Arr::get($stripeSubscription, 'cancel_at')),
            'auto_renew' => !(bool) Arr::get($stripeSubscription, 'cancel_at_period_end', false),
            'status' => $status,
        ])->saveOrFail();

        return $subscription->fresh(['order', 'server']);
    }

    public function createMirrorRenewalOrder(BillingSubscription $subscription, array $invoice): BillingOrder
    {
        return BillingOrder::query()->create([
            'user_id' => $subscription->user_id,
            'billing_node_config_id' => $subscription->billing_node_config_id,
            'billing_game_profile_id' => $subscription->billing_game_profile_id,
            'node_id' => $subscription->order?->node_id,
            'egg_id' => $subscription->order?->egg_id,
            'server_id' => $subscription->server_id,
            'order_type' => BillingOrder::TYPE_RENEWAL,
            'status' => BillingOrder::STATUS_AWAITING_PAYMENT,
            'billing_profile_snapshot' => [],
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
            'total' => round(($invoice['subtotal'] ?? 0) / 100, 2),
        ]);
    }

    private function mapTimestamp(mixed $timestamp): ?CarbonImmutable
    {
        if (!is_numeric($timestamp)) {
            return null;
        }

        return CarbonImmutable::createFromTimestampUTC((int) $timestamp);
    }
}
