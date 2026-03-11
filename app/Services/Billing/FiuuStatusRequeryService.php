<?php

namespace Pterodactyl\Services\Billing;

use Illuminate\Support\Facades\Http;
use Pterodactyl\Models\BillingPaymentAttempt;

class FiuuStatusRequeryService
{
    public function requery(BillingPaymentAttempt $attempt, array $normalizedPayload = []): array
    {
        $url = trim((string) config('billing.fiuu.requery_url'));
        if ($url === '') {
            return [
                'verified' => false,
                'is_paid' => false,
                'provider_status' => 'requery_unavailable',
                'response' => null,
                'reason' => 'Fiuu requery URL is not configured.',
            ];
        }

        $requestPayload = array_filter([
            'merchant_id' => (string) config('billing.fiuu.merchant_id'),
            'verify_key' => (string) config('billing.fiuu.verify_key'),
            'secret_key' => (string) config('billing.fiuu.secret_key'),
            'reference' => $normalizedPayload['reference'] ?? $attempt->checkout_reference,
            'transaction_id' => $normalizedPayload['transaction_id'] ?? null,
            'amount' => $normalizedPayload['amount'] ?? number_format((float) $attempt->invoice->grand_total, 2, '.', ''),
            'currency' => $normalizedPayload['currency'] ?? $attempt->invoice->currency,
        ], fn ($value) => !is_null($value) && $value !== '');

        $response = Http::asForm()
            ->timeout(20)
            ->post($url, $requestPayload);

        $body = $response->json();
        if (!is_array($body)) {
            parse_str((string) $response->body(), $body);
        }

        $providerStatus = strtolower(trim((string) (
            $body['status']
            ?? $body['Status']
            ?? $body['statcode']
            ?? $body['StatCode']
            ?? $body['payment_status']
            ?? 'unknown'
        )));

        $isPaid = in_array($providerStatus, ['22', '00', 'captured', 'paid', 'success', 'successful'], true);

        return [
            'verified' => $response->successful(),
            'is_paid' => $response->successful() && $isPaid,
            'provider_status' => $providerStatus,
            'response' => $body,
            'reason' => $response->successful() ? null : $response->body(),
        ];
    }
}
