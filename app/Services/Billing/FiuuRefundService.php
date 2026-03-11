<?php

namespace Pterodactyl\Services\Billing;

use Illuminate\Support\Facades\Http;
use Pterodactyl\Models\BillingPayment;
use Pterodactyl\Exceptions\DisplayException;

class FiuuRefundService
{
    public function refund(BillingPayment $payment, float $amount, string $refundNumber, ?string $reason = null): array
    {
        $url = trim((string) config('billing.fiuu.refund_url'));
        if ($url === '') {
            throw new DisplayException('Fiuu refund URL has not been configured.');
        }

        $payload = array_filter([
            'merchant_id' => (string) config('billing.fiuu.merchant_id'),
            'verify_key' => (string) config('billing.fiuu.verify_key'),
            'secret_key' => (string) config('billing.fiuu.secret_key'),
            'transaction_id' => $payment->provider_transaction_id,
            'reference' => $payment->provider_order_id,
            'amount' => number_format($amount, 2, '.', ''),
            'currency' => $payment->currency,
            'refund_reference' => $refundNumber,
            'reason' => $reason,
        ], fn ($value) => !is_null($value) && $value !== '');

        $response = Http::asForm()
            ->timeout(20)
            ->post($url, $payload);

        $body = $response->json();
        if (!is_array($body)) {
            parse_str((string) $response->body(), $body);
        }

        $status = strtolower((string) ($body['status'] ?? $body['Status'] ?? ''));

        return [
            'successful' => $response->successful() && in_array($status, ['success', 'successful', 'ok', 'completed'], true),
            'status' => $status !== '' ? $status : ($response->successful() ? 'requested' : 'failed'),
            'refund_id' => $body['refund_id'] ?? $body['RefundID'] ?? null,
            'response' => $body,
            'raw_body' => $response->body(),
        ];
    }
}
