<?php

namespace Pterodactyl\Services\Billing;

use Illuminate\Support\Arr;
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

        $merchantId = trim((string) config('billing.fiuu.merchant_id'));
        $secretKey = trim((string) config('billing.fiuu.secret_key'));
        $verifyKey = trim((string) config('billing.fiuu.verify_key'));
        $transactionId = trim((string) ($payment->provider_transaction_id ?? ''));

        if ($merchantId === '') {
            throw new DisplayException('Fiuu Merchant ID has not been configured.');
        }

        if ($secretKey === '') {
            throw new DisplayException('Fiuu secret/private key has not been configured.');
        }

        if ($transactionId === '') {
            throw new DisplayException('This payment cannot be refunded because it does not have a Fiuu transaction ID.');
        }

        $formattedAmount = number_format($amount, 2, '.', '');
        $isFullRefund = round($amount, 2) >= round((float) $payment->amount, 2);
        $payload = $isFullRefund
            ? [
                'txnID' => $transactionId,
                'domain' => $merchantId,
                'skey' => md5($transactionId . $merchantId . ($verifyKey !== '' ? $verifyKey : $secretKey)),
            ]
            : array_filter([
                'RefundType' => 'P',
                'MerchantID' => $merchantId,
                'RefID' => $refundNumber,
                'TxnID' => $transactionId,
                'Amount' => $formattedAmount,
                'Signature' => md5('P' . $merchantId . $refundNumber . $transactionId . $formattedAmount . $secretKey),
                'notify_url' => trim((string) config('billing.fiuu.callback_url')),
                'description' => $reason,
            ], fn ($value) => !is_null($value) && $value !== '');

        $targetUrl = $this->resolveRefundEndpoint($url, $isFullRefund);
        $response = $this->sendRefundRequest($targetUrl, $payload);
        $body = $this->parseRefundResponse($response->body(), $response->json());

        $statusCode = strtoupper((string) (
            Arr::get($body, 'status')
            ?? Arr::get($body, 'Status')
            ?? Arr::get($body, 'StatCode')
            ?? Arr::get($body, 'stat_code')
            ?? Arr::get($body, 'code')
            ?? Arr::get($body, 'Code')
            ?? ''
        ));
        $statusText = strtolower((string) (
            Arr::get($body, 'StatName')
            ?? Arr::get($body, 'stat_name')
            ?? Arr::get($body, 'description')
            ?? Arr::get($body, 'Description')
            ?? Arr::get($body, 'error_desc')
            ?? Arr::get($body, 'message')
            ?? Arr::get($body, 'Message')
            ?? $statusCode
        ));
        $successful = $response->successful() && in_array($statusCode, ['00', 'SUCCESS', 'SUCCESSFUL', 'OK', 'COMPLETED'], true);

        return [
            'successful' => $successful,
            'status' => $statusCode !== '' ? strtolower($statusCode) : ($response->successful() ? 'requested' : 'failed'),
            'refund_id' => $body['refund_id'] ?? $body['RefundID'] ?? $body['RefID'] ?? $refundNumber,
            'reason' => $statusText !== '' ? $statusText : null,
            'response' => $body,
            'raw_body' => $response->body(),
        ];
    }

    private function resolveRefundEndpoint(string $url, bool $isFullRefund): string
    {
        if ($isFullRefund) {
            return str_contains(strtolower($url), '/refundapi/refund.php')
                ? $url
                : (preg_replace('#/refundapi/index\.php$#i', '/refundAPI/refund.php', $url) ?: $url);
        }

        return str_contains(strtolower($url), '/refundapi/index.php')
            ? $url
            : (preg_replace('#/refundapi/refund\.php$#i', '/refundAPI/index.php', $url) ?: $url);
    }

    private function parseRefundResponse(string $rawBody, mixed $jsonBody): array
    {
        if (is_array($jsonBody)) {
            return $jsonBody;
        }

        $parsed = [];
        parse_str(str_replace(["\r\n", "\n", "\r"], '&', $rawBody), $parsed);

        if ($parsed !== []) {
            return $parsed;
        }

        foreach (preg_split('/\r\n|\r|\n/', $rawBody) as $line) {
            if (!str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = array_pad(explode('=', $line, 2), 2, null);
            if ($key !== null && $key !== '') {
                $parsed[$key] = $value;
            }
        }

        return $parsed;
    }

    private function sendRefundRequest(string $url, array $payload)
    {
        $client = Http::timeout(20);

        if (str_contains(strtolower($url), '/refundapi/index.php')) {
            return $client->get($url, $payload);
        }

        if (str_contains(strtolower($url), '/refundapi/refund.php')) {
            return $client->asMultipart()->post($url, collect($payload)
                ->map(fn ($value, $key) => [
                    'name' => $key,
                    'contents' => (string) $value,
                ])->values()->all());
        }

        return $client->asForm()->post($url, $payload);
    }
}
