<?php

namespace Pterodactyl\Services\Billing;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Pterodactyl\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Arr;
use Pterodactyl\Models\BillingRefund;
use Pterodactyl\Models\BillingSubscription;
use Pterodactyl\Models\BillingOrder;
use Pterodactyl\Models\BillingInvoice;
use Pterodactyl\Models\BillingPayment;
use Pterodactyl\Models\BillingGatewayEvent;
use Pterodactyl\Models\BillingPaymentAttempt;
use Pterodactyl\Jobs\Billing\BillingProvisionAfterPaymentJob;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Notifications\BillingRefundCompleted;
use Pterodactyl\Notifications\BillingPaymentReceipt;
use Pterodactyl\Notifications\BillingPaymentActionRequired;

class BillingPaymentService
{
    private const CHECKOUT_REUSE_WINDOW_MINUTES = 15;

    public function __construct(
        private BillingPaymentAttemptService $attemptService,
        private BillingInvoiceNumberService $numberService,
        private BillingInvoiceService $invoiceService,
        private BillingSubscriptionService $subscriptionService,
        private FiuuCheckoutService $fiuuCheckoutService,
        private FiuuCallbackVerificationService $callbackVerificationService,
        private FiuuStatusRequeryService $statusRequeryService,
        private FiuuRecurringChargeService $recurringChargeService,
        private FiuuRefundService $refundService,
    ) {
    }

    public function startCheckout(BillingInvoice $invoice): array
    {
        $invoice->loadMissing('user', 'attempts');

        if ($invoice->status === BillingInvoice::STATUS_PAID) {
            throw new DisplayException('This invoice has already been paid.');
        }

        if ((float) $invoice->grand_total <= 0) {
            throw new DisplayException('This invoice does not require online payment.');
        }

        if (!in_array($invoice->status, [
            BillingInvoice::STATUS_DRAFT,
            BillingInvoice::STATUS_OPEN,
            BillingInvoice::STATUS_FAILED,
            BillingInvoice::STATUS_PROCESSING,
        ], true)) {
            throw new DisplayException('This invoice cannot be sent to checkout in its current state.');
        }

        $reusableAttempt = $this->findReusableCheckoutAttempt($invoice);
        if ($reusableAttempt) {
            $checkout = $this->fiuuCheckoutService->resumeCheckout($reusableAttempt, $reusableAttempt->raw_request_payload ?? []);
            $attempt = $this->attemptService->markRedirected($reusableAttempt, $checkout['request_payload'] ?? $checkout['payload']);

            if ($invoice->status === BillingInvoice::STATUS_DRAFT) {
                $invoice->forceFill([
                    'status' => BillingInvoice::STATUS_OPEN,
                    'issued_at' => $invoice->issued_at ?? CarbonImmutable::now(),
                ])->saveOrFail();
            }

            return [
                'attempt' => $attempt,
                'checkout' => $checkout,
                'invoice' => $invoice->fresh(),
            ];
        }

        $attempt = $this->attemptService->create($invoice, FiuuCheckoutService::PROVIDER);
        $checkout = $this->fiuuCheckoutService->buildCheckout($invoice, $attempt);
        $attempt = $this->attemptService->markRedirected($attempt, $checkout['request_payload'] ?? $checkout['payload']);

        if ($invoice->status === BillingInvoice::STATUS_DRAFT) {
            $invoice->forceFill([
                'status' => BillingInvoice::STATUS_OPEN,
                'issued_at' => $invoice->issued_at ?? CarbonImmutable::now(),
            ])->saveOrFail();
        }

        return [
            'attempt' => $attempt,
            'checkout' => $checkout,
            'invoice' => $invoice->fresh(),
        ];
    }

    public function retryCheckout(BillingInvoice $invoice): array
    {
        return $this->startCheckout($invoice);
    }

    private function findReusableCheckoutAttempt(BillingInvoice $invoice): ?BillingPaymentAttempt
    {
        $attempt = BillingPaymentAttempt::query()
            ->where('invoice_id', $invoice->id)
            ->where('provider', FiuuCheckoutService::PROVIDER)
            ->whereIn('status', [
                BillingPaymentAttempt::STATUS_INITIATED,
                BillingPaymentAttempt::STATUS_REDIRECTED,
            ])
            ->latest('id')
            ->first();

        if (!$attempt || !is_array($attempt->raw_request_payload) || $attempt->raw_request_payload === []) {
            return null;
        }

        $cutoff = CarbonImmutable::now()->subMinutes(self::CHECKOUT_REUSE_WINDOW_MINUTES);
        if (!$attempt->created_at || $attempt->created_at->lt($cutoff)) {
            return null;
        }

        return $attempt;
    }

    public function settleZeroAmountInvoice(BillingInvoice $invoice, ?string $reason = null): BillingPayment
    {
        $invoice->loadMissing('order', 'subscription');

        if ((float) $invoice->grand_total > 0) {
            throw new DisplayException('Only zero-amount invoices can be settled without payment.');
        }

        if ($invoice->status === BillingInvoice::STATUS_PAID) {
            $existingPayment = $invoice->payments()->latest('id')->first();
            if ($existingPayment) {
                return $existingPayment;
            }
        }

        return DB::transaction(function () use ($invoice, $reason) {
            return $this->recordVerifiedPayment($invoice, null, [
                'provider' => 'system',
                'provider_transaction_id' => null,
                'provider_order_id' => $invoice->invoice_number,
                'provider_payment_method' => 'no_charge',
                'provider_status' => 'no_charge',
                'amount' => 0.0,
                'currency' => $invoice->currency,
                'raw_response' => [
                    'type' => 'no_charge_invoice',
                    'reason' => $reason ?? 'Invoice total is zero and was settled internally.',
                ],
                'gateway_context' => [],
            ]);
        });
    }

    public function chargeRecurringInvoice(BillingInvoice $invoice): array
    {
        $invoice->loadMissing('subscription', 'user', 'attempts');

        if ($invoice->type !== BillingInvoice::TYPE_RENEWAL || !$invoice->subscription) {
            throw new DisplayException('Only renewal invoices can be charged automatically.');
        }

        if (!$invoice->subscription->auto_renew) {
            throw new DisplayException('Auto-renew is not enabled for this subscription.');
        }

        if (blank($invoice->subscription->gateway_token_reference)) {
            throw new DisplayException('No recurring payment token is stored for this subscription.');
        }

        $attempt = $this->attemptService->create($invoice, FiuuCheckoutService::PROVIDER, ['mode' => 'recurring']);
        $response = $this->recurringChargeService->charge($invoice, $invoice->subscription);

        if (!$response['successful']) {
            $this->attemptService->markVerifiedFailed($attempt, $response['reason'] ?? 'Recurring charge failed.');
            $invoice = $this->invoiceService->markFailed($invoice, $response['reason'] ?? 'Recurring charge failed.');
            $invoice->user?->notify(new BillingPaymentActionRequired(
                $invoice,
                $response['reason'] ?? 'Automatic renewal payment could not be completed.'
            ));

            return [
                'processed' => false,
                'attempt' => $attempt->fresh(),
                'invoice' => $invoice,
                'reason' => $response['reason'] ?? 'Recurring charge failed.',
                'response' => $response,
            ];
        }

        $payment = DB::transaction(function () use ($invoice, $attempt, $response) {
            return $this->recordVerifiedPayment($invoice, $attempt, [
                'provider_transaction_id' => $response['transaction_id'],
                'provider_order_id' => $attempt->checkout_reference,
                'provider_payment_method' => $response['payment_method'],
                'provider_status' => $response['status'],
                'amount' => (float) $invoice->grand_total,
                'currency' => $invoice->currency,
                'raw_response' => $response['response'] ?: ['raw_body' => $response['raw_body']],
                'gateway_context' => $response['response'] ?? [],
            ]);
        });

        $this->sendPaymentReceiptOnce($payment);

        return [
            'processed' => true,
            'attempt' => $attempt->fresh(),
            'invoice' => $invoice->fresh(['order', 'subscription']),
            'payment' => $payment,
            'response' => $response,
        ];
    }

    public function handleFiuuCallback(array $payload, bool $isReplay = false): array
    {
        $normalized = $this->callbackVerificationService->normalize($payload);
        $dedupeKey = sha1(json_encode([
            'provider' => FiuuCheckoutService::PROVIDER,
            'reference' => $normalized['reference'],
            'transaction_id' => $normalized['transaction_id'],
            'status' => $normalized['transaction_id'] ? null : $normalized['status'],
            'amount' => $normalized['transaction_id'] ? null : $normalized['amount'],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        $event = BillingGatewayEvent::query()->firstOrCreate(
            ['dedupe_key' => $dedupeKey],
            [
                'provider' => FiuuCheckoutService::PROVIDER,
                'event_type' => 'callback',
                'provider_event_id' => $normalized['transaction_id'] ?: null,
                'provider_transaction_id' => $normalized['transaction_id'] ?: null,
                'status' => BillingGatewayEvent::STATUS_RECEIVED,
                'payload' => $payload,
            ]
        );

        if (!$isReplay && $event->status === BillingGatewayEvent::STATUS_PROCESSED) {
            return [
                'processed' => true,
                'message' => 'This callback has already been processed.',
                'event' => $event,
            ];
        }

        /** @var BillingPaymentAttempt|null $attempt */
        $attempt = BillingPaymentAttempt::query()
            ->with('invoice.order', 'invoice.subscription')
            ->where('checkout_reference', $normalized['reference'])
            ->latest('id')
            ->first();

        if (!$attempt) {
            $event->forceFill([
                'status' => BillingGatewayEvent::STATUS_FAILED,
                'processing_error' => 'Unable to match the callback to a payment attempt.',
            ])->saveOrFail();

            return [
                'processed' => false,
                'message' => 'Payment attempt could not be located.',
                'event' => $event,
            ];
        }

        $attempt = $this->attemptService->markCallbackReceived($attempt, $payload);
        $invoice = $attempt->invoice;
        $existingPayment = $this->findExistingSuccessfulPayment($invoice, $normalized, $attempt);

        if ($existingPayment) {
            $this->attemptService->markVerifiedPaid(
                $attempt,
                $existingPayment,
                Arr::wrap($payload)
            );

            $event->forceFill([
                'status' => BillingGatewayEvent::STATUS_PROCESSED,
                'processed_at' => CarbonImmutable::now(),
                'processing_error' => null,
            ])->saveOrFail();

            return [
                'processed' => true,
                'message' => 'Payment had already been verified earlier.',
                'event' => $event,
                'payment' => $existingPayment->fresh(['invoice']),
                'invoice' => $invoice->fresh(['order', 'subscription']),
            ];
        }

        $signatureVerified = $this->callbackVerificationService->verifySignature($normalized);
        $requery = $this->statusRequeryService->requery($attempt, $normalized);
        if (!$requery['verified'] || !$requery['is_paid']) {
            $reasons = [];
            if (!$signatureVerified) {
                $reasons[] = 'Callback signature verification failed.';
            }
            $reasons[] = $requery['reason'] ?? 'Fiuu status requery did not confirm payment.';
            $failureReason = implode(' ', array_filter($reasons));

            $attempt = $this->attemptService->markVerifiedFailed(
                $attempt,
                $failureReason,
                $payload
            );

            $invoice->forceFill(['status' => BillingInvoice::STATUS_PROCESSING])->saveOrFail();
            $event->forceFill([
                'status' => BillingGatewayEvent::STATUS_FAILED,
                'processing_error' => $failureReason,
            ])->saveOrFail();

            return [
                'processed' => false,
                'message' => 'Payment is still waiting for verified confirmation.',
                'event' => $event,
                'invoice' => $invoice,
                'attempt' => $attempt,
            ];
        }

        if (!$signatureVerified) {
            Log::warning('Fiuu callback signature verification failed, but the official requery endpoint confirmed the payment. Continuing with verified payment flow.', [
                'invoice_id' => $invoice->id,
                'attempt_id' => $attempt->id,
                'reference' => $normalized['reference'] ?? null,
                'transaction_id' => $normalized['transaction_id'] ?? null,
            ]);
        }

        $payment = DB::transaction(function () use ($attempt, $invoice, $normalized, $requery, $payload, $signatureVerified) {
            return $this->recordVerifiedPayment($invoice, $attempt, [
                'provider_transaction_id' => $normalized['transaction_id'] ?: null,
                'provider_order_id' => $attempt->checkout_reference,
                'provider_payment_method' => $normalized['payment_method'] ?: null,
                'provider_status' => $requery['provider_status'],
                'amount' => (float) ($requery['amount'] ?? $normalized['amount']),
                'currency' => $requery['currency'] ?? $normalized['currency'],
                'raw_response' => [
                    'callback' => $payload,
                    'requery' => $requery['response'],
                    'signature_verified' => $signatureVerified,
                ],
                'gateway_context' => $normalized['raw'] ?? [],
            ]);
        });

        $event->forceFill([
            'status' => BillingGatewayEvent::STATUS_PROCESSED,
            'processed_at' => CarbonImmutable::now(),
            'processing_error' => null,
        ])->saveOrFail();

        $this->sendPaymentReceiptOnce($payment);

        return [
            'processed' => true,
            'message' => 'Payment verified successfully.',
            'event' => $event,
            'payment' => $payment,
            'invoice' => $invoice->fresh(['order', 'subscription']),
        ];
    }

    public function refundPayment(
        BillingPayment $payment,
        float $amount,
        ?string $reason = null,
        ?User $requestedBy = null,
        bool $notifyUser = true
    ): BillingRefund
    {
        $payment->loadMissing('invoice.user', 'invoice.order', 'invoice.subscription');

        if ($amount <= 0) {
            throw new DisplayException('Refund amount must be greater than zero.');
        }

        $completedRefundTotal = $this->getCompletedRefundTotal($payment);
        $remainingRefundable = round(max((float) $payment->amount - $completedRefundTotal, 0), 2);

        if ($remainingRefundable <= 0) {
            throw new DisplayException('This payment has already been fully refunded.');
        }

        if ($amount > $remainingRefundable) {
            throw new DisplayException(sprintf(
                'Refund amount cannot exceed the remaining refundable balance of %.2f %s.',
                $remainingRefundable,
                $payment->currency
            ));
        }

        $refund = DB::transaction(function () use ($payment, $amount, $reason, $requestedBy, $notifyUser) {
            $refund = BillingRefund::query()->create([
                'payment_id' => $payment->id,
                'refund_number' => $this->numberService->nextRefundNumber(),
                'amount' => round($amount, 2),
                'reason' => $reason,
                'status' => BillingRefund::STATUS_REQUESTED,
                'requested_by' => $requestedBy?->id,
                'requested_at' => CarbonImmutable::now(),
            ]);

            $response = $this->refundService->refund($payment, $amount, $refund->refund_number, $reason);

            $refund->forceFill([
                'provider_refund_id' => $response['refund_id'],
                'status' => $response['successful'] ? BillingRefund::STATUS_COMPLETED : BillingRefund::STATUS_FAILED,
                'completed_at' => $response['successful'] ? CarbonImmutable::now() : null,
                'raw_response' => $response['response'] ?: ['raw_body' => $response['raw_body']],
            ])->saveOrFail();

            $payment->refresh();
            $completedRefundTotal = $this->getCompletedRefundTotal($payment);

            $payment->forceFill([
                'status' => $this->determineRefundPaymentStatus($payment, $completedRefundTotal, (bool) $response['successful']),
            ])->saveOrFail();

            $invoice = $this->invoiceService->applyRefundStatus(
                $payment->invoice->fresh(['order', 'subscription.server'])
            );

            if ($response['successful']) {
                $invoice = $this->handleSuccessfulRefundOutcome($invoice);
                if ($notifyUser) {
                    $payment->invoice->user?->notify(
                        new BillingRefundCompleted($refund->fresh(['payment.invoice.subscription.server', 'payment.invoice.order', 'payment']))
                    );
                }
            }

            return $refund->fresh(['payment', 'requestedBy']);
        });

        if ($refund->status === BillingRefund::STATUS_COMPLETED) {
            $invoice = BillingInvoice::query()
                ->with(['order', 'subscription.server', 'payments.refunds'])
                ->find($payment->invoice_id);

            if ($invoice) {
                $this->cascadeUpgradeRefundsForTerminatedSubscription($invoice, $requestedBy);
            }
        }

        return $refund->fresh(['payment', 'requestedBy']);
    }

    private function recordVerifiedPayment(BillingInvoice $invoice, ?BillingPaymentAttempt $attempt, array $payload): BillingPayment
    {
        $payment = BillingPayment::query()
            ->where('invoice_id', $invoice->id)
            ->when(!empty($payload['provider_transaction_id']), fn ($query) => $query->where('provider_transaction_id', $payload['provider_transaction_id']))
            ->first();
        $rawGatewayResponse = $this->mergeGatewayResponses(
            $payment?->raw_gateway_response,
            $payload['raw_response'] ?? null
        );

        if (!$payment) {
            $payment = BillingPayment::query()->create([
                'invoice_id' => $invoice->id,
                'provider' => $payload['provider'] ?? FiuuCheckoutService::PROVIDER,
                'payment_number' => $this->numberService->nextPaymentNumber(),
                'provider_transaction_id' => $payload['provider_transaction_id'] ?: null,
                'provider_order_id' => $payload['provider_order_id'] ?: ($attempt?->checkout_reference ?? $invoice->invoice_number),
                'provider_payment_method' => $payload['provider_payment_method'] ?: null,
                'provider_status' => $payload['provider_status'],
                'amount' => round((float) $payload['amount'], 2),
                'currency' => $payload['currency'],
                'status' => BillingPayment::STATUS_VERIFIED_PAID,
                'paid_at' => CarbonImmutable::now(),
                'raw_gateway_response' => $rawGatewayResponse,
            ]);
        } else {
            $payment->forceFill([
                'provider' => $payload['provider'] ?? $payment->provider,
                'provider_transaction_id' => $payload['provider_transaction_id'] ?: $payment->provider_transaction_id,
                'provider_order_id' => $payload['provider_order_id'] ?: $payment->provider_order_id ?: ($attempt?->checkout_reference ?? $invoice->invoice_number),
                'provider_payment_method' => $payload['provider_payment_method'] ?: $payment->provider_payment_method,
                'provider_status' => $payload['provider_status'],
                'amount' => round((float) $payload['amount'], 2),
                'currency' => $payload['currency'],
                'status' => BillingPayment::STATUS_VERIFIED_PAID,
                'paid_at' => $payment->paid_at ?? CarbonImmutable::now(),
                'raw_gateway_response' => $rawGatewayResponse,
            ])->saveOrFail();
        }

        if ($attempt) {
            $this->attemptService->markVerifiedPaid($attempt, $payment, Arr::wrap($payload['raw_response'] ?? []));
        }
        $this->invoiceService->markPaid($invoice, $payment);
        $this->syncSubscriptionGatewayReferences($invoice->fresh(['subscription']), $payload['gateway_context'] ?? []);

        if ($invoice->order && $invoice->type === BillingInvoice::TYPE_NEW_SERVER) {
            $invoice->order->loadMissing('server');

            if ($invoice->order->server_id && $invoice->order->server) {
                $invoice->order->forceFill([
                    'status' => BillingOrder::STATUS_PROVISIONED,
                    'provisioned_at' => $invoice->order->provisioned_at ?? CarbonImmutable::now(),
                    'provision_failure_code' => null,
                    'provision_failure_message' => null,
                ])->saveOrFail();
            } else {
                $invoice->order->forceFill([
                    'status' => BillingOrder::STATUS_QUEUED_PROVISION,
                ])->saveOrFail();

                BillingProvisionAfterPaymentJob::dispatch($invoice->order->id);
            }
        }

        if ($invoice->type === BillingInvoice::TYPE_UPGRADE && $invoice->subscription && $invoice->order) {
            $this->subscriptionService->applyPaidUpgrade($invoice->subscription->fresh(), $invoice->order->fresh());
            $invoice->order->forceFill([
                'status' => BillingOrder::STATUS_PROVISIONED,
                'provisioned_at' => $invoice->order->provisioned_at ?? CarbonImmutable::now(),
                'provision_failure_code' => null,
                'provision_failure_message' => null,
            ])->saveOrFail();
        }

        if ($invoice->type === BillingInvoice::TYPE_RENEWAL && $invoice->subscription) {
            $this->subscriptionService->applyPaidRenewal($invoice->subscription->fresh());
            if ($invoice->order) {
                $invoice->order->forceFill([
                    'status' => BillingOrder::STATUS_PROVISIONED,
                    'provisioned_at' => $invoice->order->provisioned_at ?? CarbonImmutable::now(),
                    'provision_failure_code' => null,
                    'provision_failure_message' => null,
                ])->saveOrFail();
            }
        }

        return $payment->fresh(['invoice']);
    }

    private function syncSubscriptionGatewayReferences(BillingInvoice $invoice, array $gatewayContext = []): void
    {
        if (!$invoice->subscription) {
            return;
        }

        $tokenReference = $gatewayContext['token_reference']
            ?? $gatewayContext['token']
            ?? $gatewayContext['token_id']
            ?? $gatewayContext['cc_token']
            ?? null;

        $customerReference = $gatewayContext['customer_reference']
            ?? $gatewayContext['customer_id']
            ?? $gatewayContext['cust_ref']
            ?? null;

        if (!$tokenReference && !$customerReference) {
            return;
        }

        $invoice->subscription->forceFill([
            'gateway_provider' => FiuuCheckoutService::PROVIDER,
            'gateway_token_reference' => $tokenReference ?: $invoice->subscription->gateway_token_reference,
            'gateway_customer_reference' => $customerReference ?: $invoice->subscription->gateway_customer_reference,
        ])->saveOrFail();
    }

    private function findExistingSuccessfulPayment(
        BillingInvoice $invoice,
        array $normalized,
        BillingPaymentAttempt $attempt
    ): ?BillingPayment {
        return BillingPayment::query()
            ->where('invoice_id', $invoice->id)
            ->where('status', BillingPayment::STATUS_VERIFIED_PAID)
            ->where(function ($query) use ($normalized, $attempt, $invoice) {
                if (!empty($normalized['transaction_id'])) {
                    $query->where('provider_transaction_id', $normalized['transaction_id']);

                    return;
                }

                $query->where('provider_order_id', $attempt->checkout_reference ?? $invoice->invoice_number);
            })
            ->first();
    }

    private function mergeGatewayResponses(mixed $existing, mixed $incoming): ?array
    {
        $existing = is_array($existing) ? $existing : [];
        $incoming = is_array($incoming) ? $incoming : [];

        $merged = array_replace_recursive($existing, $incoming);

        return $merged === [] ? null : $merged;
    }

    private function sendPaymentReceiptOnce(BillingPayment $payment): void
    {
        $payment->loadMissing('invoice.user', 'invoice.subscription', 'invoice.order');

        if ($this->hasPaymentReceiptBeenSent($payment)) {
            return;
        }

        $payment->invoice->user?->notify(
            new BillingPaymentReceipt($payment->invoice->fresh(['subscription', 'order']), $payment)
        );

        $payload = is_array($payment->raw_gateway_response) ? $payment->raw_gateway_response : [];
        $payload['receipt_notified_at'] = CarbonImmutable::now()->toIso8601String();

        $payment->forceFill([
            'raw_gateway_response' => $payload,
        ])->saveOrFail();
    }

    private function hasPaymentReceiptBeenSent(BillingPayment $payment): bool
    {
        return filled(data_get($payment->raw_gateway_response, 'receipt_notified_at'));
    }

    private function getCompletedRefundTotal(BillingPayment $payment): float
    {
        return round((float) $payment->refunds()
            ->where('status', BillingRefund::STATUS_COMPLETED)
            ->sum('amount'), 2);
    }

    private function determineRefundPaymentStatus(BillingPayment $payment, float $completedRefundTotal, bool $lastAttemptSuccessful): string
    {
        if ($completedRefundTotal >= (float) $payment->amount) {
            return BillingPayment::STATUS_REFUNDED;
        }

        if ($completedRefundTotal > 0) {
            return BillingPayment::STATUS_REFUND_PENDING;
        }

        return $lastAttemptSuccessful
            ? BillingPayment::STATUS_REFUND_PENDING
            : BillingPayment::STATUS_REFUND_FAILED;
    }

    private function handleSuccessfulRefundOutcome(BillingInvoice $invoice): BillingInvoice
    {
        $invoice->loadMissing('order', 'subscription.server');
        $subscription = $invoice->subscription ?: $this->resolveSubscriptionForInvoice($invoice);

        if ($invoice->status !== BillingInvoice::STATUS_REFUNDED) {
            return $invoice;
        }

        if ($invoice->order) {
            $invoice->order->forceFill([
                'status' => BillingOrder::STATUS_REFUNDED,
            ])->saveOrFail();
        }

        if (!$subscription) {
            return $invoice->fresh(['order', 'subscription.server']);
        }

        if (in_array($invoice->type, [BillingInvoice::TYPE_NEW_SERVER, BillingInvoice::TYPE_RENEWAL], true)) {
            $this->subscriptionService->scheduleTerminationAfterRefund($subscription->fresh());
        }

        if ($invoice->type === BillingInvoice::TYPE_UPGRADE) {
            $this->subscriptionService->revertLatestUpgradeAfterRefund($subscription->fresh());
        }

        return $invoice->fresh(['order', 'subscription.server']);
    }

    private function cascadeUpgradeRefundsForTerminatedSubscription(BillingInvoice $invoice, ?User $requestedBy = null): void
    {
        if (!in_array($invoice->type, [BillingInvoice::TYPE_NEW_SERVER, BillingInvoice::TYPE_RENEWAL], true)) {
            return;
        }

        $subscription = $invoice->subscription ?: $this->resolveSubscriptionForInvoice($invoice);
        if (!$subscription?->server_id) {
            return;
        }

        BillingInvoice::query()
            ->with(['payments.refunds', 'order'])
            ->where('type', BillingInvoice::TYPE_UPGRADE)
            ->whereIn('status', [
                BillingInvoice::STATUS_PAID,
                BillingInvoice::STATUS_PARTIALLY_REFUNDED,
            ])
            ->whereHas('order', function ($query) use ($subscription) {
                $query->where('user_id', $subscription->user_id)
                    ->where('server_id', $subscription->server_id)
                    ->where('order_type', BillingOrder::TYPE_UPGRADE);
            })
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->get()
            ->each(function (BillingInvoice $upgradeInvoice) use ($requestedBy, $invoice) {
                foreach ($upgradeInvoice->payments->sortByDesc('id') as $upgradePayment) {
                    $remainingRefundable = round(
                        max((float) $upgradePayment->amount - $this->getCompletedRefundTotal($upgradePayment), 0),
                        2
                    );

                    if ($remainingRefundable <= 0) {
                        continue;
                    }

                    try {
                        $this->refundPayment(
                            $upgradePayment->fresh(['invoice.user', 'invoice.order', 'invoice.subscription']),
                            $remainingRefundable,
                            sprintf(
                                'Automatically refunded because server invoice %s was fully refunded and the subscription is being terminated.',
                                $invoice->invoice_number
                            ),
                            $requestedBy,
                            false
                        );
                    } catch (\Throwable $exception) {
                        report($exception);
                        Log::warning('Billing upgrade refund cascade failed after base server refund.', [
                            'base_invoice_id' => $invoice->id,
                            'base_invoice_number' => $invoice->invoice_number,
                            'upgrade_invoice_id' => $upgradeInvoice->id,
                            'upgrade_invoice_number' => $upgradeInvoice->invoice_number,
                            'payment_id' => $upgradePayment->id,
                            'message' => $exception->getMessage(),
                        ]);
                    }
                }
            });
    }

    private function resolveSubscriptionForInvoice(BillingInvoice $invoice): ?BillingSubscription
    {
        if ($invoice->subscription) {
            return $invoice->subscription;
        }

        if ($invoice->order?->id) {
            $subscription = BillingSubscription::query()
                ->where('billing_order_id', $invoice->order->id)
                ->first();
            if ($subscription) {
                return $subscription;
            }
        }

        if ($invoice->order?->server_id) {
            return BillingSubscription::query()
                ->where('server_id', $invoice->order->server_id)
                ->latest('id')
                ->first();
        }

        return null;
    }
}
