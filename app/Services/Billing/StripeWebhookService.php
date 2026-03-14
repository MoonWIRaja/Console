<?php

namespace Pterodactyl\Services\Billing;

use Throwable;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Models\User;
use Stripe\Event;
use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;
use Pterodactyl\Models\BillingInvoice;
use Pterodactyl\Models\BillingPayment;
use Pterodactyl\Models\BillingGatewayEvent;
use Pterodactyl\Models\BillingPaymentAttempt;
use Pterodactyl\Models\BillingSubscription;
use Symfony\Component\HttpKernel\Exception\HttpException;

class StripeWebhookService
{
    public function __construct(
        private StripeClientFactory $stripe,
        private StripeCustomerService $customerService,
        private StripeInvoiceSyncService $invoiceSyncService,
        private StripeSubscriptionSyncService $subscriptionSyncService,
        private BillingPaymentService $paymentService,
        private BillingPaymentAttemptService $attemptService,
    ) {
    }

    public function handle(string $payload, ?string $signatureHeader = null): array
    {
        $event = $this->constructEvent($payload, $signatureHeader);
        $eventData = $event->toArray();

        $gatewayEvent = BillingGatewayEvent::query()->firstOrCreate(
            [
                'provider' => 'stripe',
                'provider_event_id' => $event->id,
            ],
            [
                'event_type' => $event->type,
                'provider_transaction_id' => Arr::get($eventData, 'data.object.id'),
                'dedupe_key' => sha1($event->id),
                'status' => BillingGatewayEvent::STATUS_RECEIVED,
                'payload' => $eventData,
            ]
        );

        if ($gatewayEvent->status === BillingGatewayEvent::STATUS_PROCESSED) {
            return [
                'processed' => true,
                'message' => 'Stripe webhook already processed.',
                'event' => $gatewayEvent,
            ];
        }

        try {
            $result = match ($event->type) {
                'checkout.session.completed' => $this->handleCheckoutSessionCompleted($eventData),
                'invoice.paid' => $this->handleInvoicePaid($eventData),
                'invoice.payment_failed' => $this->handleInvoicePaymentFailed($eventData),
                'customer.subscription.updated', 'customer.subscription.deleted' => $this->handleSubscriptionUpdated($eventData),
                'charge.refunded', 'refund.updated' => $this->handleRefundUpdated($eventData),
                default => [
                    'processed' => true,
                    'message' => 'Stripe webhook ignored.',
                ],
            };

            $gatewayEvent->forceFill([
                'status' => BillingGatewayEvent::STATUS_PROCESSED,
                'processed_at' => CarbonImmutable::now(),
                'processing_error' => null,
            ])->saveOrFail();

            return $result + ['event' => $gatewayEvent];
        } catch (Throwable $exception) {
            $gatewayEvent->forceFill([
                'status' => BillingGatewayEvent::STATUS_FAILED,
                'processing_error' => $exception->getMessage(),
            ])->saveOrFail();

            throw $exception;
        }
    }

    public function reconcileCheckoutReturn(string $sessionId): array
    {
        $session = $this->stripe->make()->checkout->sessions->retrieve($sessionId, [
            'expand' => [
                'invoice.payment_intent.payment_method',
                'subscription.latest_invoice.payment_intent.payment_method',
            ],
        ])->toArray();

        $result = $this->handleCheckoutSessionCompleted([
            'data' => [
                'object' => $session,
            ],
        ]);

        $stripeInvoice = $this->resolveStripeInvoiceFromSessionPayload($session);
        if (!$stripeInvoice) {
            return $result;
        }

        $invoiceStatus = (string) Arr::get($stripeInvoice, 'status', '');
        $paymentStatus = (string) Arr::get($session, 'payment_status', '');

        if ($invoiceStatus === 'paid' || $paymentStatus === 'paid') {
            return $this->handleInvoicePaid([
                'data' => [
                    'object' => $stripeInvoice,
                ],
            ]);
        }

        if (in_array($invoiceStatus, ['open', 'uncollectible', 'void'], true) || $paymentStatus === 'unpaid') {
            return $this->handleInvoicePaymentFailed([
                'data' => [
                    'object' => $stripeInvoice,
                ],
            ]);
        }

        return $result;
    }

    public function reconcilePendingInvoicesForUser(User $user, int $limit = 5): void
    {
        BillingInvoice::query()
            ->where('user_id', $user->id)
            ->where('provider', 'stripe')
            ->whereIn('status', [
                BillingInvoice::STATUS_DRAFT,
                BillingInvoice::STATUS_OPEN,
                BillingInvoice::STATUS_PROCESSING,
                BillingInvoice::STATUS_FAILED,
            ])
            ->where(function ($query) {
                $query->whereNotNull('provider_checkout_session_id')
                    ->orWhereNotNull('provider_invoice_id');
            })
            ->latest('id')
            ->limit($limit)
            ->get()
            ->each(function (BillingInvoice $invoice): void {
                try {
                    if ($invoice->provider_checkout_session_id) {
                        $this->reconcileCheckoutReturn($invoice->provider_checkout_session_id);

                        return;
                    }

                    if (!$invoice->provider_invoice_id) {
                        return;
                    }

                    $stripeInvoice = $this->stripe->make()->invoices->retrieve($invoice->provider_invoice_id, [
                        'expand' => ['payment_intent.payment_method'],
                    ])->toArray();

                    $status = (string) Arr::get($stripeInvoice, 'status', '');
                    if ($status === 'paid') {
                        $this->handleInvoicePaid([
                            'data' => [
                                'object' => $stripeInvoice,
                            ],
                        ]);

                        return;
                    }

                    if (in_array($status, ['open', 'uncollectible', 'void'], true)) {
                        $this->handleInvoicePaymentFailed([
                            'data' => [
                                'object' => $stripeInvoice,
                            ],
                        ]);
                    }
                } catch (Throwable $exception) {
                    Log::warning('Failed to reconcile pending Stripe invoice.', [
                        'invoice_id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                        'message' => $exception->getMessage(),
                    ]);
                }
            });
    }

    private function constructEvent(string $payload, ?string $signatureHeader = null): Event
    {
        $secret = $this->stripe->webhookSecret();
        if ($secret && $signatureHeader) {
            try {
                return Webhook::constructEvent($payload, $signatureHeader, $secret);
            } catch (SignatureVerificationException $exception) {
                throw new HttpException(422, $exception->getMessage(), $exception);
            }
        }

        return Event::constructFrom(json_decode($payload, true, 512, JSON_THROW_ON_ERROR));
    }

    private function handleCheckoutSessionCompleted(array $event): array
    {
        $session = Arr::get($event, 'data.object', []);
        $customerId = $this->extractProviderId(Arr::get($session, 'customer'));
        $subscriptionId = $this->extractProviderId(Arr::get($session, 'subscription'));
        $invoiceId = $this->extractProviderId(Arr::get($session, 'invoice'));
        $attempt = BillingPaymentAttempt::query()
            ->with('invoice.order', 'invoice.subscription')
            ->where('provider_session_id', Arr::get($session, 'id'))
            ->first();

        if (!$attempt || !$attempt->invoice) {
            return [
                'processed' => false,
                'message' => 'No matching local checkout attempt was found for this Stripe session.',
            ];
        }

        $invoice = $attempt->invoice;
        $user = $invoice->user ?: User::query()->find($invoice->user_id);
        if ($user && $customerId) {
            $this->customerService->syncByProviderCustomerId($customerId, $user);
        }

        $subscription = $invoice->subscription
            ?: $this->subscriptionSyncService->ensureDraftSubscription(
                $invoice,
                $customerId ?? '',
                (string) ($invoice->subscription?->provider_price_id ?? '')
            );

        $subscription->forceFill([
            'gateway_provider' => 'stripe',
            'gateway_customer_reference' => $customerId,
            'provider_subscription_id' => $subscriptionId,
            'provider_status' => 'checkout_completed',
        ])->saveOrFail();

        $invoice->forceFill([
            'provider' => 'stripe',
            'provider_checkout_session_id' => Arr::get($session, 'id'),
            'provider_invoice_id' => $invoiceId,
            'provider_status' => Arr::get($session, 'status', 'complete'),
            'subscription_id' => $subscription->id,
            'billing_profile_snapshot' => $this->invoiceSyncService->buildSnapshot($user, $session),
        ])->saveOrFail();

        return [
            'processed' => true,
            'message' => 'Stripe checkout session linked successfully.',
            'invoice' => $invoice->fresh(['subscription']),
        ];
    }

    private function handleInvoicePaid(array $event): array
    {
        $stripeInvoice = Arr::get($event, 'data.object', []);
        $subscriptionId = $this->extractProviderId(Arr::get($stripeInvoice, 'subscription')) ?? '';
        $invoice = BillingInvoice::query()
            ->with(['subscription.user', 'order', 'payments.refunds'])
            ->where('provider', 'stripe')
            ->where('provider_invoice_id', $this->extractProviderId(Arr::get($stripeInvoice, 'id')))
            ->first();

        if (!$invoice && $subscriptionId !== '') {
            $subscription = BillingSubscription::query()
                ->with(['user', 'order'])
                ->where('provider_subscription_id', $subscriptionId)
                ->first();

            if ($subscription) {
                $invoice = BillingInvoice::query()
                    ->with(['subscription.user', 'order', 'payments.refunds'])
                    ->where('subscription_id', $subscription->id)
                    ->whereIn('type', [BillingInvoice::TYPE_NEW_SERVER, BillingInvoice::TYPE_UPGRADE])
                    ->whereIn('status', [
                        BillingInvoice::STATUS_DRAFT,
                        BillingInvoice::STATUS_OPEN,
                        BillingInvoice::STATUS_PROCESSING,
                        BillingInvoice::STATUS_FAILED,
                    ])
                    ->latest('id')
                    ->first();

                if (!$invoice && Arr::get($stripeInvoice, 'billing_reason') === 'subscription_cycle') {
                    $invoice = $this->invoiceSyncService->findOrMirrorRecurringInvoice($subscription, $stripeInvoice);
                }
            }
        }

        if (!$invoice) {
            return [
                'processed' => false,
                'message' => 'No matching local invoice could be found for this Stripe payment.',
            ];
        }

        if ($invoice->status === BillingInvoice::STATUS_PAID) {
            return [
                'processed' => true,
                'message' => 'Stripe invoice was already mirrored as paid.',
                'invoice' => $invoice,
            ];
        }

        $invoice->loadMissing('user', 'subscription.user', 'order');
        $snapshot = $this->invoiceSyncService->buildSnapshot($invoice->user, $stripeInvoice);
        $invoice = $this->invoiceSyncService->sync($invoice, $stripeInvoice, $snapshot);

        if ($invoice->subscription && $subscriptionId !== '') {
            $subscriptionPayload = $this->stripe->make()->subscriptions->retrieve($subscriptionId, [
                'expand' => ['items.data.price.product'],
            ])->toArray();
            $this->subscriptionSyncService->syncFromStripeSubscription($invoice->subscription, $subscriptionPayload);
        }

        $attempt = BillingPaymentAttempt::query()
            ->where('invoice_id', $invoice->id)
            ->where('provider', 'stripe')
            ->latest('id')
            ->first();

        $payment = DB::transaction(function () use ($invoice, $attempt, $stripeInvoice) {
            return $this->paymentService->recordStripeInvoicePayment($invoice, $stripeInvoice, $attempt);
        });

        return [
            'processed' => true,
            'message' => 'Stripe invoice mirrored as paid.',
            'invoice' => $invoice->fresh(['order', 'subscription']),
            'payment' => $payment,
        ];
    }

    private function handleInvoicePaymentFailed(array $event): array
    {
        $stripeInvoice = Arr::get($event, 'data.object', []);
        $invoice = BillingInvoice::query()
            ->with(['subscription', 'order'])
            ->where('provider', 'stripe')
            ->where('provider_invoice_id', $this->extractProviderId(Arr::get($stripeInvoice, 'id')))
            ->first();

        if (!$invoice && ($subscriptionId = $this->extractProviderId(Arr::get($stripeInvoice, 'subscription')))) {
            $subscription = BillingSubscription::query()
                ->where('provider_subscription_id', $subscriptionId)
                ->first();

            if ($subscription && Arr::get($stripeInvoice, 'billing_reason') === 'subscription_cycle') {
                $invoice = $this->invoiceSyncService->findOrMirrorRecurringInvoice($subscription, $stripeInvoice);
            }
        }

        if (!$invoice) {
            return [
                'processed' => false,
                'message' => 'No matching local invoice could be found for this Stripe failure event.',
            ];
        }

        $this->invoiceSyncService->sync($invoice, $stripeInvoice, $this->invoiceSyncService->buildSnapshot($invoice->user, $stripeInvoice));
        $this->paymentService->markStripeInvoiceFailed(
            $invoice,
            $stripeInvoice,
            (string) Arr::get($stripeInvoice, 'last_finalization_error.message', 'Stripe payment failed.')
        );

        return [
            'processed' => true,
            'message' => 'Stripe failed invoice mirrored locally.',
            'invoice' => $invoice->fresh(['order', 'subscription']),
        ];
    }

    private function handleSubscriptionUpdated(array $event): array
    {
        $stripeSubscription = Arr::get($event, 'data.object', []);
        $subscription = BillingSubscription::query()
            ->where('provider_subscription_id', $this->extractProviderId(Arr::get($stripeSubscription, 'id')))
            ->first();

        if (!$subscription) {
            return [
                'processed' => false,
                'message' => 'No matching local subscription could be found for this Stripe subscription event.',
            ];
        }

        $subscription = $this->subscriptionSyncService->syncFromStripeSubscription($subscription, $stripeSubscription);

        return [
            'processed' => true,
            'message' => 'Stripe subscription mirrored locally.',
            'subscription' => $subscription,
        ];
    }

    private function handleRefundUpdated(array $event): array
    {
        $refundPayload = Arr::get($event, 'data.object', []);
        $chargeId = (string) ($this->extractProviderId(Arr::get($refundPayload, 'charge')) ?: $this->extractProviderId(Arr::get($refundPayload, 'id')));
        $payment = BillingPayment::query()
            ->where('provider', 'stripe')
            ->where(function ($query) use ($chargeId, $refundPayload) {
                $query->where('provider_charge_id', $chargeId)
                    ->orWhere('provider_payment_intent_id', (string) $this->extractProviderId(Arr::get($refundPayload, 'payment_intent')));
            })
            ->first();

        if (!$payment) {
            return [
                'processed' => false,
                'message' => 'No matching local payment could be found for this Stripe refund event.',
            ];
        }

        $refund = $payment->refunds()
            ->where('provider_refund_id', Arr::get($refundPayload, 'id'))
            ->latest('id')
            ->first();

        if ($refund) {
            $refund->forceFill([
                'provider_refund_id' => Arr::get($refundPayload, 'id'),
                'provider_refund_status' => Arr::get($refundPayload, 'status'),
                'raw_response' => $refundPayload,
            ])->saveOrFail();
        }

        return [
            'processed' => true,
            'message' => 'Stripe refund state mirrored locally.',
        ];
    }

    private function resolveStripeInvoiceFromSessionPayload(array $session): ?array
    {
        $invoice = Arr::get($session, 'invoice');
        if (is_array($invoice) && $invoice !== []) {
            return $invoice;
        }

        if (is_string($invoice) && $invoice !== '') {
            return $this->stripe->make()->invoices->retrieve($invoice, [
                'expand' => ['payment_intent.payment_method'],
            ])->toArray();
        }

        $subscription = Arr::get($session, 'subscription');
        if (is_array($subscription) && $subscription !== []) {
            $latestInvoice = Arr::get($subscription, 'latest_invoice');
            if (is_array($latestInvoice) && $latestInvoice !== []) {
                return $latestInvoice;
            }

            if (is_string($latestInvoice) && $latestInvoice !== '') {
                return $this->stripe->make()->invoices->retrieve($latestInvoice, [
                    'expand' => ['payment_intent.payment_method'],
                ])->toArray();
            }
        }

        if (is_string($subscription) && $subscription !== '') {
            $stripeSubscription = $this->stripe->make()->subscriptions->retrieve($subscription, [
                'expand' => ['latest_invoice.payment_intent.payment_method'],
            ])->toArray();

            $latestInvoice = Arr::get($stripeSubscription, 'latest_invoice');
            if (is_array($latestInvoice) && $latestInvoice !== []) {
                return $latestInvoice;
            }

            if (is_string($latestInvoice) && $latestInvoice !== '') {
                return $this->stripe->make()->invoices->retrieve($latestInvoice, [
                    'expand' => ['payment_intent.payment_method'],
                ])->toArray();
            }
        }

        return null;
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
