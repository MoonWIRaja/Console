<?php

namespace Pterodactyl\Services\Billing;

use Throwable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Models\BillingInvoice;
use Pterodactyl\Models\BillingPaymentAttempt;
use Pterodactyl\Models\BillingSubscription;
use Pterodactyl\Exceptions\DisplayException;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\InvalidRequestException;

class StripeCheckoutService
{
    public const PROVIDER = 'stripe';

    public function __construct(
        private StripeClientFactory $stripe,
        private StripeCustomerService $customerService,
        private StripePriceService $priceService,
        private StripeSubscriptionSyncService $subscriptionSyncService,
        private BillingCatalogService $catalogService,
    ) {
    }

    public function buildCheckout(BillingInvoice $invoice, BillingPaymentAttempt $attempt): array
    {
        try {
            return match ($invoice->type) {
                BillingInvoice::TYPE_NEW_SERVER => $this->buildNewServerCheckout($invoice, $attempt),
                BillingInvoice::TYPE_RENEWAL => $this->buildRenewalMigrationCheckout($invoice, $attempt),
                BillingInvoice::TYPE_UPGRADE => $this->buildUpgradePayment($invoice, $attempt),
                default => throw new DisplayException('Stripe checkout is not available for this invoice type yet.'),
            };
        } catch (DisplayException $exception) {
            throw $exception;
        } catch (ApiErrorException $exception) {
            throw new DisplayException($this->formatStripeApiError($exception));
        } catch (Throwable $exception) {
            report($exception);

            throw new DisplayException('Stripe checkout could not be prepared right now. Please try again in a moment.');
        }
    }

    public function resumeHostedInvoice(BillingInvoice $invoice): ?array
    {
        if (!$invoice->hosted_invoice_url) {
            return null;
        }

        return [
            'provider' => self::PROVIDER,
            'method' => 'GET',
            'url' => $invoice->hosted_invoice_url,
            'payload' => [],
            'request_payload' => [],
        ];
    }

    private function buildNewServerCheckout(BillingInvoice $invoice, BillingPaymentAttempt $attempt): array
    {
        $invoice->loadMissing('user', 'order');

        $customer = $this->customerService->ensureForUser($invoice->user);
        $priceBundle = $this->priceService->createRecurringPrice(
            sprintf('%s server subscription', $invoice->order?->server_name ?? $invoice->invoice_number),
            (float) $invoice->subtotal,
            [
                'local_invoice_id' => (string) $invoice->id,
                'local_order_id' => (string) ($invoice->billing_order_id ?? ''),
                'local_attempt_id' => (string) $attempt->id,
                'local_type' => $invoice->type,
            ]
        );

        $draftSubscription = $this->subscriptionSyncService->ensureDraftSubscription(
            $invoice,
            $customer->provider_customer_id,
            $priceBundle['price']->id
        );

        $session = $this->createCheckoutSession([
            'mode' => 'subscription',
            'customer' => $customer->provider_customer_id,
            'client_reference_id' => $attempt->checkout_reference,
            'payment_method_types' => ['card'],
            'billing_address_collection' => 'required',
            'phone_number_collection' => ['enabled' => true],
            'tax_id_collection' => ['enabled' => true],
            'automatic_tax' => ['enabled' => (bool) config('billing.stripe.automatic_tax_enabled', true)],
            'success_url' => $this->resolveSuccessUrl(),
            'cancel_url' => $this->resolveCancelUrl(),
            'customer_update' => [
                'address' => 'auto',
                'name' => 'auto',
            ],
            'line_items' => [[
                'price' => $priceBundle['price']->id,
                'quantity' => 1,
            ]],
            'metadata' => [
                'local_invoice_id' => (string) $invoice->id,
                'local_order_id' => (string) ($invoice->billing_order_id ?? ''),
                'local_subscription_id' => (string) $draftSubscription->id,
                'local_attempt_id' => (string) $attempt->id,
                'local_checkout_reference' => $attempt->checkout_reference,
                'local_type' => $invoice->type,
            ],
            'subscription_data' => [
                'metadata' => [
                    'local_invoice_id' => (string) $invoice->id,
                    'local_order_id' => (string) ($invoice->billing_order_id ?? ''),
                    'local_subscription_id' => (string) $draftSubscription->id,
                    'local_type' => $invoice->type,
                ],
            ],
        ]);

        $sessionPayload = $session->toArray();

        $invoice->forceFill([
            'provider' => self::PROVIDER,
            'provider_checkout_session_id' => $session->id,
            'provider_status' => Arr::get($sessionPayload, 'status'),
        ])->saveOrFail();

        $attempt->forceFill([
            'provider_session_id' => $session->id,
            'attempt_mode' => 'checkout',
        ])->saveOrFail();

        return [
            'provider' => self::PROVIDER,
            'method' => 'GET',
            'url' => $session->url,
            'payload' => [],
            'request_payload' => [
                'session_id' => $session->id,
                'provider_session_id' => $session->id,
                'attempt_mode' => 'checkout',
                'session_url' => $session->url,
                'price_id' => $priceBundle['price']->id,
                'subscription_id' => $draftSubscription->id,
            ],
        ];
    }

    private function buildRenewalMigrationCheckout(BillingInvoice $invoice, BillingPaymentAttempt $attempt): array
    {
        $invoice->loadMissing('user', 'subscription');
        $subscription = $invoice->subscription;
        if (!$subscription) {
            throw new DisplayException('Renewal invoice is not linked to a subscription.');
        }

        if ($subscription->gateway_provider === self::PROVIDER && $invoice->hosted_invoice_url) {
            return $this->resumeHostedInvoice($invoice) ?? throw new DisplayException('Stripe hosted invoice URL is missing.');
        }

        $customer = $this->customerService->ensureForUser($invoice->user);
        $priceBundle = $this->priceService->createRecurringPrice(
            sprintf('%s renewal subscription', $subscription->server_name),
            (float) $invoice->subtotal,
            [
                'local_invoice_id' => (string) $invoice->id,
                'local_order_id' => (string) ($invoice->billing_order_id ?? ''),
                'local_subscription_id' => (string) $subscription->id,
                'local_type' => $invoice->type,
            ]
        );

        $session = $this->createCheckoutSession([
            'mode' => 'subscription',
            'customer' => $customer->provider_customer_id,
            'client_reference_id' => $attempt->checkout_reference,
            'payment_method_types' => ['card'],
            'billing_address_collection' => 'required',
            'phone_number_collection' => ['enabled' => true],
            'tax_id_collection' => ['enabled' => true],
            'automatic_tax' => ['enabled' => (bool) config('billing.stripe.automatic_tax_enabled', true)],
            'success_url' => $this->resolveSuccessUrl(),
            'cancel_url' => $this->resolveCancelUrl(),
            'customer_update' => [
                'address' => 'auto',
                'name' => 'auto',
            ],
            'line_items' => [[
                'price' => $priceBundle['price']->id,
                'quantity' => 1,
            ]],
            'metadata' => [
                'local_invoice_id' => (string) $invoice->id,
                'local_order_id' => (string) ($invoice->billing_order_id ?? ''),
                'local_subscription_id' => (string) $subscription->id,
                'local_attempt_id' => (string) $attempt->id,
                'local_checkout_reference' => $attempt->checkout_reference,
                'local_type' => $invoice->type,
                'migration_source' => (string) ($subscription->gateway_provider ?: 'legacy'),
            ],
            'subscription_data' => [
                'metadata' => [
                    'local_invoice_id' => (string) $invoice->id,
                    'local_order_id' => (string) ($invoice->billing_order_id ?? ''),
                    'local_subscription_id' => (string) $subscription->id,
                    'local_type' => $invoice->type,
                    'migration_source' => (string) ($subscription->gateway_provider ?: 'legacy'),
                ],
            ],
        ]);

        $sessionPayload = $session->toArray();

        $subscription->forceFill([
            'gateway_customer_reference' => $customer->provider_customer_id,
            'provider_price_id' => $priceBundle['price']->id,
            'migration_source' => $subscription->gateway_provider,
            'migration_state' => 'checkout_pending',
        ])->saveOrFail();

        $invoice->forceFill([
            'provider' => self::PROVIDER,
            'provider_checkout_session_id' => $session->id,
            'provider_status' => Arr::get($sessionPayload, 'status'),
        ])->saveOrFail();

        $attempt->forceFill([
            'provider_session_id' => $session->id,
            'attempt_mode' => 'migration',
        ])->saveOrFail();

        return [
            'provider' => self::PROVIDER,
            'method' => 'GET',
            'url' => $session->url,
            'payload' => [],
            'request_payload' => [
                'provider_session_id' => $session->id,
                'attempt_mode' => 'migration',
                'session_id' => $session->id,
                'session_url' => $session->url,
                'price_id' => $priceBundle['price']->id,
            ],
        ];
    }

    private function buildUpgradePayment(BillingInvoice $invoice, BillingPaymentAttempt $attempt): array
    {
        $invoice->loadMissing('subscription.nodeConfig', 'order', 'user');
        $subscription = $invoice->subscription;

        if (!$subscription || !$subscription->provider_subscription_id || !$subscription->provider_subscription_item_id) {
            throw new DisplayException('This subscription is not linked to Stripe yet. Migrate it to Stripe before creating an upgrade invoice.');
        }

        $targetRecurringTotal = round(
            (float) $invoice->order->cpu_total
            + (float) $invoice->order->memory_total
            + (float) $invoice->order->disk_total,
            2
        );

        $priceBundle = $this->priceService->createRecurringPrice(
            sprintf('%s upgraded subscription', $subscription->server_name),
            $targetRecurringTotal,
            [
                'local_invoice_id' => (string) $invoice->id,
                'local_order_id' => (string) ($invoice->billing_order_id ?? ''),
                'local_subscription_id' => (string) $subscription->id,
                'local_type' => $invoice->type,
            ]
        );

        $updatedSubscription = $this->stripe->make()->subscriptions->update($subscription->provider_subscription_id, [
            'items' => [[
                'id' => $subscription->provider_subscription_item_id,
                'price' => $priceBundle['price']->id,
            ]],
            'proration_behavior' => 'always_invoice',
            'payment_behavior' => 'default_incomplete',
            'metadata' => [
                'local_invoice_id' => (string) $invoice->id,
                'local_order_id' => (string) ($invoice->billing_order_id ?? ''),
                'local_subscription_id' => (string) $subscription->id,
                'local_type' => $invoice->type,
            ],
            'expand' => ['latest_invoice.payment_intent'],
        ]);

        $subscriptionPayload = $updatedSubscription->toArray();
        $latestInvoice = Arr::get($subscriptionPayload, 'latest_invoice', []);
        $hostedInvoiceUrl = Arr::get($latestInvoice, 'hosted_invoice_url');
        if (!$hostedInvoiceUrl) {
            throw new DisplayException('Stripe did not return a hosted invoice URL for this upgrade.');
        }

        $invoice->forceFill([
            'provider' => self::PROVIDER,
            'provider_invoice_id' => Arr::get($latestInvoice, 'id'),
            'provider_payment_intent_id' => Arr::get($latestInvoice, 'payment_intent.id', Arr::get($latestInvoice, 'payment_intent')),
            'provider_status' => Arr::get($latestInvoice, 'status'),
            'hosted_invoice_url' => $hostedInvoiceUrl,
            'invoice_pdf_url' => Arr::get($latestInvoice, 'invoice_pdf'),
        ])->saveOrFail();

        $subscription->forceFill([
            'provider_price_id' => $priceBundle['price']->id,
        ])->saveOrFail();

        $attempt->forceFill([
            'provider_session_id' => Arr::get($latestInvoice, 'id'),
            'attempt_mode' => 'upgrade',
        ])->saveOrFail();

        return [
            'provider' => self::PROVIDER,
            'method' => 'GET',
            'url' => $hostedInvoiceUrl,
            'payload' => [],
            'request_payload' => [
                'provider_session_id' => Arr::get($latestInvoice, 'id'),
                'attempt_mode' => 'upgrade',
                'hosted_invoice_url' => $hostedInvoiceUrl,
                'provider_invoice_id' => Arr::get($latestInvoice, 'id'),
                'price_id' => $priceBundle['price']->id,
            ],
        ];
    }

    private function resolveSuccessUrl(): string
    {
        $base = trim((string) config('billing.stripe.success_url', ''));
        if ($base === '') {
            $base = rtrim((string) config('app.url', ''), '/') . '/billing/gateways/stripe/return';
        }

        $separator = str_contains($base, '?') ? '&' : '?';

        return str_contains($base, '{CHECKOUT_SESSION_ID}')
            ? $base
            : $base . $separator . 'session_id={CHECKOUT_SESSION_ID}';
    }

    private function resolveCancelUrl(): string
    {
        $url = trim((string) config('billing.stripe.cancel_url', ''));

        return $url !== '' ? $url : rtrim((string) config('app.url', ''), '/') . '/billing';
    }

    private function createCheckoutSession(array $payload): Session
    {
        try {
            return $this->stripe->make()->checkout->sessions->create($payload);
        } catch (InvalidRequestException $exception) {
            if (!$this->shouldRetryWithoutAutomaticTax($payload, $exception)) {
                throw $exception;
            }

            Log::warning('Stripe Checkout rejected automatic tax; retrying checkout session without automatic_tax.', [
                'message' => $exception->getMessage(),
            ]);

            $payload['automatic_tax'] = ['enabled' => false];

            return $this->stripe->make()->checkout->sessions->create($payload);
        }
    }

    private function shouldRetryWithoutAutomaticTax(array $payload, InvalidRequestException $exception): bool
    {
        if (!Arr::get($payload, 'automatic_tax.enabled', false)) {
            return false;
        }

        $message = strtolower($exception->getMessage());

        return str_contains($message, 'stripe tax is not supported')
            || str_contains($message, 'automatic_tax')
            || str_contains($message, 'tax is not supported for your account country');
    }

    private function formatStripeApiError(ApiErrorException $exception): string
    {
        $message = trim((string) $exception->getMessage());

        if ($message === '') {
            return 'Stripe returned an unexpected billing error. Please try again in a moment.';
        }

        if (str_contains(strtolower($message), 'stripe tax is not supported')) {
            return 'This Stripe account does not support Stripe Tax. Disable Stripe Tax in billing gateway settings or use the automatic fallback that has now been applied.';
        }

        return sprintf('Stripe error: %s', $message);
    }
}
