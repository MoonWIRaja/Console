<?php

namespace Pterodactyl\Services\Billing;

use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Pterodactyl\Models\User;
use Pterodactyl\Models\BillingOrder;
use Pterodactyl\Models\BillingProfile;
use Pterodactyl\Models\BillingInvoice;
use Pterodactyl\Models\BillingSubscription;
use Pterodactyl\Models\BillingInvoiceItem;

class StripeInvoiceSyncService
{
    public function __construct(
        private BillingProfileService $profileService,
        private StripeCustomerService $customerService,
        private StripeSubscriptionSyncService $subscriptionSyncService,
    ) {
    }

    public function sync(BillingInvoice $invoice, array $stripeInvoice, ?array $snapshot = null): BillingInvoice
    {
        $invoice->forceFill([
            'provider' => 'stripe',
            'provider_invoice_id' => $this->extractProviderId(Arr::get($stripeInvoice, 'id')) ?? $invoice->provider_invoice_id,
            'provider_payment_intent_id' => $this->extractProviderId(Arr::get($stripeInvoice, 'payment_intent')) ?? $invoice->provider_payment_intent_id,
            'hosted_invoice_url' => Arr::get($stripeInvoice, 'hosted_invoice_url', $invoice->hosted_invoice_url),
            'invoice_pdf_url' => Arr::get($stripeInvoice, 'invoice_pdf', $invoice->invoice_pdf_url),
            'provider_status' => Arr::get($stripeInvoice, 'status', $invoice->provider_status),
            'subtotal' => $this->toAmount(Arr::get($stripeInvoice, 'subtotal_excluding_tax', Arr::get($stripeInvoice, 'subtotal', 0))),
            'tax_total' => $this->resolveTaxTotal($stripeInvoice),
            'grand_total' => $this->toAmount(Arr::get($stripeInvoice, 'total', 0)),
            'currency' => strtoupper((string) Arr::get($stripeInvoice, 'currency', $invoice->currency)),
            'issued_at' => $this->toDate(Arr::get($stripeInvoice, 'created')) ?? $invoice->issued_at,
            'due_at' => $this->toDate(Arr::get($stripeInvoice, 'due_date')) ?? $invoice->due_at,
            'billing_profile_snapshot' => $snapshot ?: $invoice->billing_profile_snapshot,
        ])->saveOrFail();

        if ($invoice->items()->count() < 1) {
            $invoice->items()->create([
                'type' => BillingInvoiceItem::TYPE_BASE_PLAN,
                'description' => Arr::get($stripeInvoice, 'lines.data.0.description', ucfirst(str_replace('_', ' ', $invoice->type)) . ' invoice'),
                'quantity' => 1,
                'unit_amount' => $invoice->subtotal,
                'line_subtotal' => $invoice->subtotal,
                'meta' => [
                    'provider' => 'stripe',
                    'provider_invoice_id' => $invoice->provider_invoice_id,
                ],
            ]);
        }

        return $invoice->fresh(['items', 'order', 'subscription']);
    }

    public function findOrMirrorRecurringInvoice(BillingSubscription $subscription, array $stripeInvoice): BillingInvoice
    {
        $existing = BillingInvoice::query()
            ->where('provider', 'stripe')
            ->where('provider_invoice_id', Arr::get($stripeInvoice, 'id'))
            ->first();

        if ($existing) {
            return $this->sync($existing, $stripeInvoice, $this->buildSnapshot($subscription->user, $stripeInvoice));
        }

        $order = $this->subscriptionSyncService->createMirrorRenewalOrder($subscription, $stripeInvoice);
        $profile = $this->profileService->getOrCreateForUser($subscription->user);

        $invoice = BillingInvoice::query()->create([
            'invoice_number' => Arr::get($stripeInvoice, 'number') ?: 'STRIPE-' . Arr::get($stripeInvoice, 'id'),
            'user_id' => $subscription->user_id,
            'billing_profile_id' => $profile->id,
            'billing_order_id' => $order->id,
            'subscription_id' => $subscription->id,
            'provider' => 'stripe',
            'provider_invoice_id' => $this->extractProviderId(Arr::get($stripeInvoice, 'id')),
            'provider_payment_intent_id' => $this->extractProviderId(Arr::get($stripeInvoice, 'payment_intent')),
            'hosted_invoice_url' => Arr::get($stripeInvoice, 'hosted_invoice_url'),
            'invoice_pdf_url' => Arr::get($stripeInvoice, 'invoice_pdf'),
            'provider_status' => Arr::get($stripeInvoice, 'status'),
            'type' => BillingInvoice::TYPE_RENEWAL,
            'currency' => strtoupper((string) Arr::get($stripeInvoice, 'currency', config('billing.currency', 'MYR'))),
            'subtotal' => 0,
            'tax_total' => 0,
            'grand_total' => 0,
            'status' => BillingInvoice::STATUS_OPEN,
            'issued_at' => $this->toDate(Arr::get($stripeInvoice, 'created')),
            'due_at' => $this->toDate(Arr::get($stripeInvoice, 'due_date')),
            'billing_profile_snapshot' => $this->buildSnapshot($subscription->user, $stripeInvoice),
            'notes' => 'Mirrored from Stripe recurring invoice.',
        ]);

        $order->forceFill([
            'billing_invoice_id' => $invoice->id,
        ])->saveOrFail();

        return $this->sync($invoice, $stripeInvoice);
    }

    public function buildSnapshot(User $user, array $stripePayload): array
    {
        return $this->customerService->buildInvoiceSnapshot($user, [
            'name' => Arr::get($stripePayload, 'customer_name'),
            'email' => Arr::get($stripePayload, 'customer_email'),
            'phone' => Arr::get($stripePayload, 'customer_phone'),
            'address' => Arr::get($stripePayload, 'customer_address', []),
            'tax_ids' => Arr::get($stripePayload, 'customer_tax_ids', []),
        ]);
    }

    private function resolveTaxTotal(array $stripeInvoice): float
    {
        if (is_numeric(Arr::get($stripeInvoice, 'tax'))) {
            return $this->toAmount((int) Arr::get($stripeInvoice, 'tax'));
        }

        if (is_numeric(Arr::get($stripeInvoice, 'subtotal_excluding_tax'))) {
            return round(
                $this->toAmount((int) Arr::get($stripeInvoice, 'total', 0))
                - $this->toAmount((int) Arr::get($stripeInvoice, 'subtotal_excluding_tax', 0)),
                2
            );
        }

        return round(
            $this->toAmount((int) Arr::get($stripeInvoice, 'total', 0))
            - $this->toAmount((int) Arr::get($stripeInvoice, 'subtotal', 0)),
            2
        );
    }

    private function toAmount(mixed $amount): float
    {
        return round(((float) $amount) / 100, 2);
    }

    private function toDate(mixed $timestamp): ?CarbonImmutable
    {
        if (!is_numeric($timestamp)) {
            return null;
        }

        return CarbonImmutable::createFromTimestampUTC((int) $timestamp);
    }

    private function extractProviderId(mixed $value): ?string
    {
        if (is_string($value) || is_numeric($value)) {
            $id = trim((string) $value);

            return $id === '' ? null : $id;
        }

        if (is_array($value)) {
            $id = trim((string) Arr::get($value, 'id', ''));

            return $id === '' ? null : $id;
        }

        return null;
    }
}
