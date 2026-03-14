<?php

namespace Pterodactyl\Services\Billing;

use Illuminate\Support\Arr;
use Pterodactyl\Models\BillingPayment;
use Pterodactyl\Models\BillingInvoice;
use Pterodactyl\Exceptions\DisplayException;

class StripeRefundService
{
    public function __construct(
        private StripeClientFactory $stripe,
    ) {
    }

    public function refund(BillingPayment $payment, float $amount, ?string $reason = null): array
    {
        $payment->loadMissing('invoice.subscription');
        $payment = $this->hydratePaymentReferences($payment);

        $chargeId = $payment->provider_charge_id;
        $paymentIntentId = $payment->provider_payment_intent_id;

        if (!$chargeId && !$paymentIntentId) {
            throw new DisplayException('Stripe did not return a refundable charge or payment intent for this payment. This usually means the invoice was mirrored without a direct Stripe charge, or it was settled through customer balance instead of a refundable card charge.');
        }

        $refund = $this->stripe->make()->refunds->create(array_filter([
            'charge' => $chargeId ?: null,
            'payment_intent' => !$chargeId ? $paymentIntentId : null,
            'amount' => (int) round($amount * 100),
            'reason' => 'requested_by_customer',
            'metadata' => array_filter([
                'local_payment_id' => (string) $payment->id,
                'local_invoice_id' => (string) $payment->invoice_id,
                'local_reason' => $reason,
            ], static fn ($value) => !is_null($value) && $value !== ''),
        ]));

        $payload = $refund->toArray();

        return [
            'successful' => in_array(($payload['status'] ?? null), ['pending', 'succeeded'], true),
            'refund_id' => $refund->id,
            'response' => $payload,
            'raw_body' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ];
    }

    private function hydratePaymentReferences(BillingPayment $payment): BillingPayment
    {
        if (filled($payment->provider_payment_intent_id) || filled($payment->provider_charge_id)) {
            return $payment;
        }

        $invoice = $payment->invoice;
        if (!$invoice instanceof BillingInvoice || blank($invoice->provider_invoice_id)) {
            return $payment;
        }

        $stripeInvoice = $this->stripe->make()->invoices->retrieve($invoice->provider_invoice_id, [
            'expand' => [
                'payment_intent.latest_charge',
                'payment_intent.payment_method',
                'subscription',
            ],
        ])->toArray();

        $paymentIntent = $this->resolvePaymentIntentPayload($stripeInvoice);
        $paymentMethod = Arr::get($paymentIntent, 'payment_method', []);
        $paymentIntentId = $this->extractProviderId($paymentIntent)
            ?? $this->extractProviderId(Arr::get($stripeInvoice, 'payment_intent'));
        $chargeId = $this->extractProviderId(Arr::get($paymentIntent, 'latest_charge'));
        $customerId = $this->extractProviderId(Arr::get($stripeInvoice, 'customer'));
        $subscriptionId = $this->extractProviderId(Arr::get($stripeInvoice, 'subscription'));

        $payment->forceFill([
            'provider_transaction_id' => $chargeId ?: ($paymentIntentId ?: $payment->provider_transaction_id),
            'provider_payment_intent_id' => $paymentIntentId ?: $payment->provider_payment_intent_id,
            'provider_charge_id' => $chargeId ?: $payment->provider_charge_id,
            'provider_payment_method' => Arr::get($paymentMethod, 'type', $payment->provider_payment_method),
            'payment_method_type' => Arr::get($paymentMethod, 'type', $payment->payment_method_type),
            'payment_method_brand' => Arr::get($paymentMethod, 'card.brand', $payment->payment_method_brand),
            'payment_method_last4' => Arr::get($paymentMethod, 'card.last4', $payment->payment_method_last4),
            'provider_status' => Arr::get($stripeInvoice, 'status', $payment->provider_status),
            'raw_gateway_response' => $this->mergeGatewayResponses(
                $payment->raw_gateway_response,
                [
                    'stripe_refund_hydration' => [
                        'invoice' => $stripeInvoice,
                        'payment_intent' => $paymentIntent,
                    ],
                ]
            ),
        ])->saveOrFail();

        $invoice->forceFill([
            'provider' => 'stripe',
            'provider_payment_intent_id' => $paymentIntentId ?: $invoice->provider_payment_intent_id,
            'provider_status' => Arr::get($stripeInvoice, 'status', $invoice->provider_status),
            'hosted_invoice_url' => Arr::get($stripeInvoice, 'hosted_invoice_url', $invoice->hosted_invoice_url),
            'invoice_pdf_url' => Arr::get($stripeInvoice, 'invoice_pdf', $invoice->invoice_pdf_url),
        ])->saveOrFail();

        if ($invoice->subscription && ($customerId || $subscriptionId)) {
            $invoice->subscription->forceFill([
                'gateway_provider' => 'stripe',
                'gateway_customer_reference' => $customerId ?: $invoice->subscription->gateway_customer_reference,
                'provider_subscription_id' => $subscriptionId ?: $invoice->subscription->provider_subscription_id,
            ])->saveOrFail();
        }

        return $payment->fresh(['invoice.subscription']);
    }

    private function resolvePaymentIntentPayload(array $stripeInvoice): array
    {
        $paymentIntent = Arr::get($stripeInvoice, 'payment_intent');
        if (is_array($paymentIntent)) {
            return $paymentIntent;
        }

        $paymentIntentId = $this->extractProviderId($paymentIntent);
        if (!$paymentIntentId) {
            return $this->resolvePaymentIntentFromInvoicePayments($stripeInvoice);
        }

        return $this->stripe->make()->paymentIntents->retrieve($paymentIntentId, [
            'expand' => ['latest_charge', 'payment_method'],
        ])->toArray();
    }

    private function resolvePaymentIntentFromInvoicePayments(array $stripeInvoice): array
    {
        $invoiceId = $this->extractProviderId(Arr::get($stripeInvoice, 'id'));
        if (!$invoiceId) {
            return [];
        }

        $payments = $this->stripe->make()->invoicePayments->all([
            'invoice' => $invoiceId,
            'expand' => ['data.payment.payment_intent.latest_charge', 'data.payment.payment_intent.payment_method'],
        ])->toArray();

        $paymentIntent = Arr::get($payments, 'data.0.payment.payment_intent');
        if (is_array($paymentIntent)) {
            return $paymentIntent;
        }

        $paymentIntentId = $this->extractProviderId($paymentIntent);
        if (!$paymentIntentId) {
            return [];
        }

        return $this->stripe->make()->paymentIntents->retrieve($paymentIntentId, [
            'expand' => ['latest_charge', 'payment_method'],
        ])->toArray();
    }

    private function mergeGatewayResponses(mixed $existing, mixed $incoming): ?array
    {
        $existing = is_array($existing) ? $existing : [];
        $incoming = is_array($incoming) ? $incoming : [];

        $merged = array_replace_recursive($existing, $incoming);

        return $merged === [] ? null : $merged;
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
