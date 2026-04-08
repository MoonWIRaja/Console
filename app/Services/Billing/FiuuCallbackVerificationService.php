<?php

namespace Pterodactyl\Services\Billing;

class FiuuCallbackVerificationService
{
    public function normalize(array $payload): array
    {
        $rawAmount = (string) (
            $payload['Amount']
            ?? $payload['amount']
            ?? $payload['amt']
            ?? 0
        );
        $rawCurrency = strtoupper(trim((string) (
            $payload['Currency']
            ?? $payload['currency']
            ?? config('billing.currency', 'MYR')
        )));
        $domain = trim((string) (
            $payload['domain']
            ?? $payload['Domain']
            ?? $payload['MerchantID']
            ?? $payload['merchant_id']
            ?? config('billing.fiuu.merchant_id', '')
        ));

        return [
            'reference' => (string) ($payload['RefNo'] ?? $payload['reference'] ?? $payload['orderid'] ?? ''),
            'orderid' => (string) ($payload['orderid'] ?? $payload['RefNo'] ?? $payload['reference'] ?? ''),
            'transaction_id' => (string) ($payload['TranID'] ?? $payload['tranID'] ?? $payload['transaction_id'] ?? $payload['txn_id'] ?? ''),
            'status' => (string) ($payload['Status'] ?? $payload['status'] ?? $payload['StatCode'] ?? $payload['statcode'] ?? ''),
            'amount' => $this->normalizeAmount($rawAmount),
            'raw_amount' => trim($rawAmount),
            'currency' => $this->normalizeCurrency($rawCurrency),
            'raw_currency' => $rawCurrency,
            'payment_method' => (string) ($payload['channel'] ?? $payload['payment_method'] ?? ''),
            'signature' => (string) ($payload['skey'] ?? $payload['signature'] ?? $payload['vcode'] ?? ''),
            'merchant_id' => (string) ($payload['MerchantID'] ?? $payload['merchantID'] ?? $payload['merchant_id'] ?? config('billing.fiuu.merchant_id', '')),
            'domain' => $domain,
            'paydate' => trim((string) ($payload['paydate'] ?? $payload['PayDate'] ?? '')),
            'appcode' => array_key_exists('appcode', $payload) ? (string) ($payload['appcode'] ?? '') : (string) ($payload['AppCode'] ?? ''),
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
        $secretKey = trim((string) config('billing.fiuu.secret_key', ''));

        if ($secretKey === '' || $provided === '') {
            return false;
        }

        $transactionId = (string) ($normalized['transaction_id'] ?? '');
        $references = array_unique(array_filter([
            (string) ($normalized['orderid'] ?? ''),
            (string) ($normalized['reference'] ?? ''),
        ]));
        $status = (string) ($normalized['status'] ?? '');
        $domains = array_unique(array_filter([
            trim((string) ($normalized['domain'] ?? '')),
            trim((string) ($normalized['merchant_id'] ?? '')),
            trim((string) config('billing.fiuu.merchant_id', '')),
        ]));
        $amounts = array_unique(array_filter([
            trim((string) ($normalized['raw_amount'] ?? '')),
            $this->normalizeAmount($normalized['amount'] ?? 0),
        ]));
        $currencies = array_unique(array_filter([
            strtoupper(trim((string) ($normalized['raw_currency'] ?? ''))),
            strtoupper(trim((string) ($normalized['currency'] ?? config('billing.currency', 'MYR')))),
        ]));
        $paydate = trim((string) ($normalized['paydate'] ?? ''));
        $appcodes = array_unique([
            (string) ($normalized['appcode'] ?? ''),
            '',
        ]);

        foreach ($domains as $domain) {
            foreach ($references as $reference) {
                foreach ($amounts as $amount) {
                    foreach ($currencies as $currency) {
                        $key = md5($transactionId . $reference . $status . $domain . $amount . $currency);
                        foreach ($appcodes as $appcode) {
                            $candidate = md5($paydate . $domain . $key . $appcode . $secretKey);
                            if (hash_equals($candidate, $provided)) {
                                return true;
                            }
                        }
                    }
                }
            }
        }

        return false;
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
