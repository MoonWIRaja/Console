<?php

namespace Pterodactyl\Services\Billing;

use Illuminate\Support\Facades\Http;
use Pterodactyl\Models\BillingInvoice;
use Pterodactyl\Models\BillingSubscription;

class FiuuRecurringChargeService
{
    public function charge(BillingInvoice $invoice, BillingSubscription $subscription): array
    {
        $url = trim((string) config('billing.fiuu.recurring_url'));
        if ($url === '') {
            return [
                'successful' => false,
                'status' => 'recurring_unavailable',
                'transaction_id' => null,
                'payment_method' => null,
                'response' => null,
                'raw_body' => null,
                'reason' => 'Fiuu recurring URL is not configured.',
            ];
        }

        $payload = array_filter([
            'merchant_id' => (string) config('billing.fiuu.merchant_id'),
            'verify_key' => (string) config('billing.fiuu.verify_key'),
            'secret_key' => (string) config('billing.fiuu.secret_key'),
            'reference' => $invoice->invoice_number,
            'invoice_number' => $invoice->invoice_number,
            'amount' => number_format((float) $invoice->grand_total, 2, '.', ''),
            'currency' => $invoice->currency,
            'token_reference' => $subscription->gateway_token_reference,
            'customer_reference' => $subscription->gateway_customer_reference,
        ], fn ($value) => !is_null($value) && $value !== '');

        $response = Http::asForm()
            ->timeout(20)
            ->post($url, $payload);

        $body = $response->json();
        if (!is_array($body)) {
            parse_str((string) $response->body(), $body);
        }

        $status = strtolower(trim((string) (
            $body['status']
            ?? $body['Status']
            ?? $body['statcode']
            ?? $body['StatCode']
            ?? $body['payment_status']
            ?? ''
        )));

        return [
            'successful' => $response->successful() && in_array($status, ['22', '00', 'captured', 'paid', 'success', 'successful'], true),
            'status' => $status !== '' ? $status : ($response->successful() ? 'unknown' : 'failed'),
            'transaction_id' => $body['transaction_id'] ?? $body['TranID'] ?? $body['txn_id'] ?? null,
            'payment_method' => $body['channel'] ?? $body['payment_method'] ?? null,
            'response' => $body,
            'raw_body' => $response->body(),
            'reason' => $response->successful() ? null : $response->body(),
        ];
    }
}
