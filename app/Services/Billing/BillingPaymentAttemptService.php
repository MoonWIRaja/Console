<?php

namespace Pterodactyl\Services\Billing;

use Illuminate\Support\Str;
use Carbon\CarbonImmutable;
use Pterodactyl\Models\BillingInvoice;
use Pterodactyl\Models\BillingPayment;
use Pterodactyl\Models\BillingPaymentAttempt;

class BillingPaymentAttemptService
{
    public function create(BillingInvoice $invoice, string $provider, array $requestPayload = []): BillingPaymentAttempt
    {
        $attemptNumber = (int) $invoice->attempts()->max('attempt_number') + 1;

        return BillingPaymentAttempt::query()->create([
            'invoice_id' => $invoice->id,
            'provider' => $provider,
            'attempt_number' => $attemptNumber,
            'status' => BillingPaymentAttempt::STATUS_INITIATED,
            'checkout_reference' => $this->generateCheckoutReference($provider, $invoice->id, $attemptNumber),
            'provider_session_id' => $requestPayload['provider_session_id'] ?? null,
            'attempt_mode' => $requestPayload['attempt_mode'] ?? null,
            'raw_request_payload' => $requestPayload ?: null,
        ]);
    }

    public function markRedirected(BillingPaymentAttempt $attempt, array $requestPayload = []): BillingPaymentAttempt
    {
        $attempt->forceFill([
            'status' => BillingPaymentAttempt::STATUS_REDIRECTED,
            'redirected_at' => CarbonImmutable::now(),
            'provider_session_id' => $requestPayload['provider_session_id'] ?? $attempt->provider_session_id,
            'attempt_mode' => $requestPayload['attempt_mode'] ?? $attempt->attempt_mode,
            'raw_request_payload' => $requestPayload ?: $attempt->raw_request_payload,
        ])->saveOrFail();

        return $attempt->fresh();
    }

    public function markCallbackReceived(BillingPaymentAttempt $attempt, array $payload): BillingPaymentAttempt
    {
        $attempt->forceFill([
            'status' => BillingPaymentAttempt::STATUS_CALLBACK_RECEIVED,
            'callback_received_at' => CarbonImmutable::now(),
            'raw_callback_payload' => $payload,
        ])->saveOrFail();

        return $attempt->fresh();
    }

    public function markVerifiedPaid(BillingPaymentAttempt $attempt, BillingPayment $payment, array $payload): BillingPaymentAttempt
    {
        $attempt->forceFill([
            'payment_id' => $payment->id,
            'status' => BillingPaymentAttempt::STATUS_VERIFIED_PAID,
            'verified_at' => CarbonImmutable::now(),
            'raw_callback_payload' => $payload,
            'failure_reason' => null,
        ])->saveOrFail();

        return $attempt->fresh();
    }

    public function markVerifiedFailed(BillingPaymentAttempt $attempt, string $reason, array $payload = []): BillingPaymentAttempt
    {
        $attempt->forceFill([
            'status' => BillingPaymentAttempt::STATUS_VERIFIED_FAILED,
            'verified_at' => CarbonImmutable::now(),
            'failure_reason' => $reason,
            'raw_callback_payload' => $payload ?: $attempt->raw_callback_payload,
        ])->saveOrFail();

        return $attempt->fresh();
    }

    private function generateCheckoutReference(string $provider, int $invoiceId, int $attemptNumber): string
    {
        $prefix = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $provider) ?: 'PAY');
        $prefix = substr($prefix, 0, 4);

        do {
            $reference = strtoupper($prefix . Str::random(11));
        } while (BillingPaymentAttempt::query()->where('checkout_reference', $reference)->exists());

        return $reference;
    }
}
