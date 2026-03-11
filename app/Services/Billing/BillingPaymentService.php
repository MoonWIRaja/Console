<?php

namespace Pterodactyl\Services\Billing;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Pterodactyl\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Arr;
use Pterodactyl\Models\BillingRefund;
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

        if (!in_array($invoice->status, [
            BillingInvoice::STATUS_DRAFT,
            BillingInvoice::STATUS_OPEN,
            BillingInvoice::STATUS_FAILED,
            BillingInvoice::STATUS_PROCESSING,
        ], true)) {
            throw new DisplayException('This invoice cannot be sent to checkout in its current state.');
        }

        $attempt = $this->attemptService->create($invoice, FiuuCheckoutService::PROVIDER);
        $checkout = $this->fiuuCheckoutService->buildCheckout($invoice, $attempt);
        $attempt = $this->attemptService->markRedirected($attempt, $checkout['payload']);

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

        $payment->invoice->user?->notify(new BillingPaymentReceipt($payment->invoice->fresh(['subscription', 'order']), $payment));

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
            'status' => $normalized['status'],
            'amount' => $normalized['amount'],
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
        $signatureVerified = $this->callbackVerificationService->verifySignature($normalized);

        if (!$signatureVerified) {
            $this->attemptService->markVerifiedFailed($attempt, 'Callback signature verification failed.', $payload);
            $invoice->forceFill(['status' => BillingInvoice::STATUS_PROCESSING])->saveOrFail();
            $event->forceFill([
                'status' => BillingGatewayEvent::STATUS_FAILED,
                'processing_error' => 'Callback signature verification failed.',
            ])->saveOrFail();

            return [
                'processed' => false,
                'message' => 'Callback signature verification failed.',
                'event' => $event,
                'invoice' => $invoice,
            ];
        }

        $requery = $this->statusRequeryService->requery($attempt, $normalized);
        if (!$requery['verified'] || !$requery['is_paid']) {
            $attempt = $this->attemptService->markVerifiedFailed(
                $attempt,
                $requery['reason'] ?? 'Fiuu status requery did not confirm payment.',
                $payload
            );

            $invoice->forceFill(['status' => BillingInvoice::STATUS_PROCESSING])->saveOrFail();
            $event->forceFill([
                'status' => BillingGatewayEvent::STATUS_FAILED,
                'processing_error' => $requery['reason'] ?? 'Payment requery failed.',
            ])->saveOrFail();

            return [
                'processed' => false,
                'message' => 'Payment is still waiting for verified confirmation.',
                'event' => $event,
                'invoice' => $invoice,
                'attempt' => $attempt,
            ];
        }

        $payment = DB::transaction(function () use ($attempt, $invoice, $normalized, $requery, $payload) {
            return $this->recordVerifiedPayment($invoice, $attempt, [
                'provider_transaction_id' => $normalized['transaction_id'] ?: null,
                'provider_order_id' => $attempt->checkout_reference,
                'provider_payment_method' => $normalized['payment_method'] ?: null,
                'provider_status' => $requery['provider_status'],
                'amount' => (float) $normalized['amount'],
                'currency' => $normalized['currency'],
                'raw_response' => [
                    'callback' => $payload,
                    'requery' => $requery['response'],
                ],
                'gateway_context' => $normalized['raw'] ?? [],
            ]);
        });

        $event->forceFill([
            'status' => BillingGatewayEvent::STATUS_PROCESSED,
            'processed_at' => CarbonImmutable::now(),
            'processing_error' => null,
        ])->saveOrFail();

        $payment->invoice->user?->notify(new BillingPaymentReceipt($payment->invoice->fresh(['subscription', 'order']), $payment));

        return [
            'processed' => true,
            'message' => 'Payment verified successfully.',
            'event' => $event,
            'payment' => $payment,
            'invoice' => $invoice->fresh(['order', 'subscription']),
        ];
    }

    public function refundPayment(BillingPayment $payment, float $amount, ?string $reason = null, ?User $requestedBy = null): BillingRefund
    {
        if ($amount <= 0) {
            throw new DisplayException('Refund amount must be greater than zero.');
        }

        if ($amount > (float) $payment->amount) {
            throw new DisplayException('Refund amount cannot exceed the original payment amount.');
        }

        return DB::transaction(function () use ($payment, $amount, $reason, $requestedBy) {
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

            $payment->forceFill([
                'status' => $response['successful']
                    ? ($amount >= (float) $payment->amount ? BillingPayment::STATUS_REFUNDED : BillingPayment::STATUS_REFUND_PENDING)
                    : BillingPayment::STATUS_REFUND_FAILED,
            ])->saveOrFail();

            $this->invoiceService->applyRefundStatus($payment->invoice->fresh());

            if ($response['successful']) {
                $payment->invoice->user?->notify(new BillingRefundCompleted($refund->fresh(['payment.invoice', 'payment'])));
            }

            return $refund->fresh(['payment', 'requestedBy']);
        });
    }

    private function recordVerifiedPayment(BillingInvoice $invoice, BillingPaymentAttempt $attempt, array $payload): BillingPayment
    {
        $payment = BillingPayment::query()
            ->where('invoice_id', $invoice->id)
            ->when(!empty($payload['provider_transaction_id']), fn ($query) => $query->where('provider_transaction_id', $payload['provider_transaction_id']))
            ->first();

        if (!$payment) {
            $payment = BillingPayment::query()->create([
                'invoice_id' => $invoice->id,
                'provider' => FiuuCheckoutService::PROVIDER,
                'payment_number' => $this->numberService->nextPaymentNumber(),
                'provider_transaction_id' => $payload['provider_transaction_id'] ?: null,
                'provider_order_id' => $payload['provider_order_id'] ?: $attempt->checkout_reference,
                'provider_payment_method' => $payload['provider_payment_method'] ?: null,
                'provider_status' => $payload['provider_status'],
                'amount' => round((float) $payload['amount'], 2),
                'currency' => $payload['currency'],
                'status' => BillingPayment::STATUS_VERIFIED_PAID,
                'paid_at' => CarbonImmutable::now(),
                'raw_gateway_response' => $payload['raw_response'] ?? null,
            ]);
        } else {
            $payment->forceFill([
                'provider_transaction_id' => $payload['provider_transaction_id'] ?: $payment->provider_transaction_id,
                'provider_order_id' => $payload['provider_order_id'] ?: $payment->provider_order_id,
                'provider_payment_method' => $payload['provider_payment_method'] ?: $payment->provider_payment_method,
                'provider_status' => $payload['provider_status'],
                'amount' => round((float) $payload['amount'], 2),
                'currency' => $payload['currency'],
                'status' => BillingPayment::STATUS_VERIFIED_PAID,
                'paid_at' => $payment->paid_at ?? CarbonImmutable::now(),
                'raw_gateway_response' => $payload['raw_response'] ?? $payment->raw_gateway_response,
            ])->saveOrFail();
        }

        $this->attemptService->markVerifiedPaid($attempt, $payment, Arr::wrap($payload['raw_response'] ?? []));
        $this->invoiceService->markPaid($invoice, $payment);
        $this->syncSubscriptionGatewayReferences($invoice->fresh(['subscription']), $payload['gateway_context'] ?? []);

        if ($invoice->order && $invoice->type === BillingInvoice::TYPE_NEW_SERVER) {
            $invoice->order->forceFill([
                'status' => BillingOrder::STATUS_QUEUED_PROVISION,
            ])->saveOrFail();

            BillingProvisionAfterPaymentJob::dispatch($invoice->order->id);
        }

        if ($invoice->type === BillingInvoice::TYPE_UPGRADE && $invoice->subscription && $invoice->order) {
            $this->subscriptionService->applyPaidUpgrade($invoice->subscription->fresh(), $invoice->order->fresh());
        }

        if ($invoice->type === BillingInvoice::TYPE_RENEWAL && $invoice->subscription) {
            $this->subscriptionService->applyPaidRenewal($invoice->subscription->fresh());
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
}
