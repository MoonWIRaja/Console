<?php

namespace Pterodactyl\Services\Billing;

class FiuuCallbackVerificationService
{
    public function normalize(array $payload): array
    {
        $amount = $this->normalizeAmount(
            $payload['Amount']
            ?? $payload['amount']
            ?? $payload['amt']
            ?? 0
        );

        return [
            'reference' => (string) ($payload['RefNo'] ?? $payload['reference'] ?? $payload['orderid'] ?? ''),
            'transaction_id' => (string) ($payload['TranID'] ?? $payload['transaction_id'] ?? $payload['txn_id'] ?? ''),
            'status' => (string) ($payload['Status'] ?? $payload['status'] ?? $payload['StatCode'] ?? ''),
            'amount' => $amount,
            'currency' => strtoupper((string) ($payload['Currency'] ?? $payload['currency'] ?? config('billing.currency', 'MYR'))),
            'payment_method' => (string) ($payload['channel'] ?? $payload['payment_method'] ?? ''),
            'signature' => (string) ($payload['skey'] ?? $payload['signature'] ?? $payload['vcode'] ?? ''),
            'merchant_id' => (string) ($payload['MerchantID'] ?? $payload['merchant_id'] ?? config('billing.fiuu.merchant_id', '')),
            'raw' => $payload,
        ];
    }

    public function isSuccessful(array $normalized): bool
    {
        $status = strtolower(trim((string) $normalized['status']));

        return in_array($status, ['22', '00', 'captured', 'paid', 'success', 'successful'], true);
    }

    public function verifySignature(array $normalized): bool
    {
        $provided = strtolower(trim((string) ($normalized['signature'] ?? '')));
        $verifyKey = strtolower(trim((string) config('billing.fiuu.verify_key', '')));

        if ($verifyKey === '' || $provided === '') {
            return true;
        }

        $transactionId = (string) ($normalized['transaction_id'] ?? '');
        $reference = (string) ($normalized['reference'] ?? '');
        $status = (string) ($normalized['status'] ?? '');
        $merchantId = (string) ($normalized['merchant_id'] ?? config('billing.fiuu.merchant_id', ''));
        $amount = $this->normalizeAmount($normalized['amount'] ?? 0);
        $currency = (string) ($normalized['currency'] ?? config('billing.currency', 'MYR'));

        $candidates = array_filter([
            md5($transactionId . $reference . $status . $merchantId . $amount . $currency . $verifyKey),
            md5($transactionId . $reference . $status . $merchantId . $amount . $currency),
            md5($transactionId . $reference . $status . $amount . $currency . $verifyKey),
            md5($amount . $merchantId . $reference . $verifyKey),
        ]);

        foreach ($candidates as $candidate) {
            if (hash_equals($candidate, $provided)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeAmount(mixed $amount): string
    {
        return number_format((float) str_replace(',', '', (string) $amount), 2, '.', '');
    }
}
