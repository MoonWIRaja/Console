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

        $transactionId = trim((string) ($normalizedPayload['transaction_id'] ?? ''));
        if ($transactionId === '') {
            return [
                'verified' => false,
                'is_paid' => false,
                'provider_status' => 'missing_transaction_id',
                'response' => null,
                'reason' => 'Fiuu callback did not contain a transaction ID.',
            ];
        }

        $domain = trim((string) ($normalizedPayload['domain'] ?? config('billing.fiuu.merchant_id', '')));
        $verifyKey = trim((string) config('billing.fiuu.verify_key', ''));
        $amount = $this->normalizeAmount(
            $normalizedPayload['amount']
            ?? $attempt->invoice->grand_total
            ?? 0
        );

        if ($domain === '' || $verifyKey === '') {
            return [
                'verified' => false,
                'is_paid' => false,
                'provider_status' => 'requery_credentials_missing',
                'response' => null,
                'reason' => 'Fiuu requery credentials are incomplete.',
            ];
        }

        $requestPayload = [
            'amount' => $amount,
            'txID' => $transactionId,
            'domain' => $domain,
            'skey' => md5($transactionId . $domain . $verifyKey . $amount),
        ];

        $response = Http::asForm()
            ->timeout(20)
            ->post($url, $requestPayload);

        $body = $this->parseBody((string) $response->body());

        $providerStatus = strtolower(trim((string) (
            $body['StatCode']
            ?? $body['status']
            ?? $body['Status']
            ?? $body['statcode']
            ?? $body['payment_status']
            ?? 'unknown'
        )));
        $providerName = strtolower(trim((string) (
            $body['StatName']
            ?? $body['status_name']
            ?? $body['payment_state']
            ?? ''
        )));

        $isPaid = in_array($providerStatus, ['22', '00', 'captured', 'paid', 'success', 'successful'], true)
            || in_array($providerName, ['captured', 'paid', 'success', 'successful'], true);

        $responseAmount = $this->normalizeAmount($body['Amount'] ?? $amount);
        $responseDomain = trim((string) ($body['Domain'] ?? $domain));
        $responseTransactionId = trim((string) ($body['TranID'] ?? $transactionId));
        $responseCurrency = $this->normalizeCurrency((string) ($body['Currency'] ?? ($normalizedPayload['currency'] ?? $attempt->invoice->currency)));

        $matchesRequest = $responseTransactionId === $transactionId
            && $responseAmount === $amount
            && strcasecmp($responseDomain, $domain) === 0;

        return [
            'verified' => $response->successful() && $matchesRequest,
            'is_paid' => $response->successful() && $matchesRequest && $isPaid,
            'provider_status' => $providerStatus,
            'response' => $body,
            'currency' => $responseCurrency,
            'amount' => $responseAmount,
            'reason' => $response->successful()
                ? ($matchesRequest ? null : 'Fiuu requery response did not match the original transaction.')
                : $response->body(),
        ];
    }

    private function parseBody(string $body): array
    {
        $decoded = json_decode($body, true);
        if (is_array($decoded) && $decoded !== []) {
            return $decoded;
        }

        $parsed = [];
        foreach (preg_split('/\r\n|\r|\n/', trim($body)) as $line) {
            if (!str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $parsed[trim($key)] = trim($value);
        }

        if ($parsed !== []) {
            return $parsed;
        }

        parse_str($body, $parsed);

        return is_array($parsed) ? $parsed : [];
    }

    private function normalizeAmount(mixed $amount): string
    {
        return number_format((float) str_replace(',', '', (string) $amount), 2, '.', '');
    }

    private function normalizeCurrency(string $currency): string
    {
        $currency = strtoupper(trim($currency));

        return match ($currency) {
            'RM' => 'MYR',
            default => $currency !== '' ? $currency : strtoupper((string) config('billing.currency', 'MYR')),
        };
    }
}
