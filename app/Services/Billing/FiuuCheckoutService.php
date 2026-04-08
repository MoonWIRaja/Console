<?php

namespace Pterodactyl\Services\Billing;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Pterodactyl\Models\BillingInvoice;
use Pterodactyl\Models\BillingPaymentAttempt;
use Pterodactyl\Exceptions\DisplayException;

class FiuuCheckoutService
{
    public const PROVIDER = 'fiuu';

    public function isEnabled(): bool
    {
        return (bool) config('billing.fiuu.enabled');
    }

    public function buildCheckout(BillingInvoice $invoice, BillingPaymentAttempt $attempt): array
    {
        $method = $this->resolveCheckoutChannel();
        $merchantUrl = $this->resolveMerchantUrl();

        $snapshot = $invoice->billing_profile_snapshot ?? [];
        $amount = number_format((float) $invoice->grand_total, 2, '.', '');
        $reference = $attempt->checkout_reference;
        $merchantId = (string) config('billing.fiuu.merchant_id');
        $verifyKey = (string) config('billing.fiuu.verify_key', '');
        $requestToken = (bool) config('billing.fiuu.request_token', true);
        $extendedVcode = (bool) config('billing.fiuu.extended_vcode', false);
        $billName = trim((string) ($snapshot['legal_name'] ?: $invoice->user?->name ?: ''));
        $billEmail = trim((string) ($snapshot['email'] ?: $invoice->user?->email ?: ''));
        $billMobile = preg_replace('/[^\d+]/', '', (string) ($snapshot['phone'] ?: ''));
        $country = strtoupper(trim((string) ($snapshot['country_code'] ?: 'MY')));
        $description = Str::limit(sprintf(
            '%s invoice %s',
            str_replace('_', ' ', strtoupper($invoice->type)),
            $invoice->invoice_number
        ), 120, '');

        if ($billName === '') {
            throw new DisplayException('Billing profile is missing a full name. Update your billing details in /account before starting checkout.');
        }

        if ($billEmail === '') {
            throw new DisplayException('Billing profile is missing an email address. Update your billing details in /account before starting checkout.');
        }

        if ($billMobile === '') {
            throw new DisplayException('Billing profile is missing a phone number. Update your billing details in /account before starting checkout.');
        }

        if ($country === '' || strlen($country) !== 2) {
            throw new DisplayException('Billing profile is missing a valid 2-letter country code. Update your billing details in /account before starting checkout.');
        }

        $payload = [
            'orderid' => $reference,
            'amount' => $amount,
            'bill_name' => $billName,
            'bill_email' => $billEmail,
            'bill_mobile' => $billMobile,
            'bill_desc' => $description,
            'country' => $country,
            'channel' => $method,
            'currency' => $invoice->currency,
            'returnurl' => (string) config('billing.fiuu.return_url'),
            'callbackurl' => (string) config('billing.fiuu.callback_url'),
            'cancelurl' => (string) config('billing.fiuu.return_url'),
            'req4token' => $requestToken ? '1' : null,
        ];

        if ($verifyKey !== '') {
            $payload['vcode'] = $this->generateVerifyCode(
                amount: $amount,
                merchantId: $merchantId,
                reference: $reference,
                verifyKey: $verifyKey,
                currency: $invoice->currency,
                extended: $extendedVcode
            );
        }

        $payload = array_filter($payload, static fn ($value) => !is_null($value) && $value !== '');

        return [
            'provider' => self::PROVIDER,
            'method' => 'GET',
            'url' => $this->temporaryCheckoutUrl($attempt),
            'payload' => [],
            'request_payload' => $payload,
            'merchant_url' => $merchantUrl,
        ];
    }

    public function resumeCheckout(BillingPaymentAttempt $attempt, array $payload): array
    {
        $merchantUrl = $this->resolveMerchantUrl();

        return [
            'provider' => self::PROVIDER,
            'method' => 'GET',
            'url' => $this->temporaryCheckoutUrl($attempt),
            'payload' => [],
            'request_payload' => array_filter($payload, static fn ($value) => !is_null($value) && $value !== ''),
            'merchant_url' => $merchantUrl,
        ];
    }

    public function resolveMerchantUrl(): string
    {
        if (!$this->isEnabled()) {
            throw new DisplayException('Fiuu checkout is not enabled yet.');
        }

        $merchantId = (string) config('billing.fiuu.merchant_id');
        if ($merchantId === '') {
            throw new DisplayException('Fiuu Merchant ID has not been configured.');
        }

        $sandbox = (bool) config('billing.fiuu.sandbox');
        $normalizedMerchantId = strtoupper(trim($merchantId));

        if ($sandbox && !str_starts_with($normalizedMerchantId, 'SB_')) {
            throw new DisplayException('Fiuu sandbox mode is enabled, but the configured Merchant ID is not a sandbox Merchant ID. Use an ID beginning with "SB_" or disable sandbox mode.');
        }

        if (!$sandbox && str_starts_with($normalizedMerchantId, 'SB_')) {
            throw new DisplayException('Fiuu production mode cannot use a sandbox Merchant ID. Disable sandbox mode only when a live Merchant ID is configured.');
        }

        $baseUrl = rtrim((string) (
            $sandbox
                ? config('billing.fiuu.checkout_urls.sandbox')
                : config('billing.fiuu.checkout_urls.production')
        ), '/');

        $merchantUrl = $this->buildMerchantUrl($baseUrl, $merchantId);
        $this->assertCheckoutUrlIsReachable($merchantUrl, $sandbox, $merchantId);

        return $merchantUrl;
    }

    private function assertCheckoutUrlIsReachable(string $url, bool $sandbox, string $merchantId): void
    {
        $probe = $this->probeCheckoutUrl($url);
        if (!$this->isRejectedCheckoutResponse($probe)) {
            return;
        }

        $mode = $sandbox ? 'sandbox' : 'production';

        throw new DisplayException(sprintf(
            'Fiuu rejected the %s checkout URL for Merchant ID "%s". Check that your Merchant ID and sandbox/live mode are correct.',
            $mode,
            $merchantId,
            $probe['status'] ?? 'unknown'
        ));
    }

    private function probeCheckoutUrl(string $url): ?array
    {
        try {
            $response = Http::timeout(10)
                ->withOptions(['allow_redirects' => false])
                ->head($url);
        } catch (\Throwable) {
            return null;
        }

        return [
            'status' => $response->status(),
            'location' => (string) $response->header('Location', ''),
        ];
    }

    private function isRejectedCheckoutResponse(?array $probe): bool
    {
        if (is_null($probe)) {
            return false;
        }

        if (in_array($probe['status'] ?? null, [401, 403, 404], true)) {
            return true;
        }

        $location = strtolower((string) ($probe['location'] ?? ''));

        return str_contains($location, '/not-found');
    }

    private function resolveCheckoutChannel(): ?string
    {
        $methods = array_values(array_filter(array_map(
            static fn ($value) => trim((string) $value),
            (array) config('billing.fiuu.enabled_methods', [])
        )));

        if (count($methods) !== 1) {
            return null;
        }

        $method = strtolower($methods[0]);
        if (in_array($method, ['all', '*', 'auto', 'choose'], true)) {
            return null;
        }

        return $methods[0];
    }

    private function buildMerchantUrl(string $baseUrl, string $merchantId): string
    {
        return rtrim($baseUrl, '/') . '/' . trim($merchantId, '/') . '/';
    }

    private function temporaryCheckoutUrl(BillingPaymentAttempt $attempt): string
    {
        return URL::route('billing.gateway.fiuu.checkout', [
            'checkoutReference' => $attempt->checkout_reference,
        ]);
    }

    private function generateVerifyCode(
        string $amount,
        string $merchantId,
        string $reference,
        string $verifyKey,
        string $currency,
        bool $extended
    ): string {
        if ($extended) {
            return md5($amount . $merchantId . $reference . $verifyKey . $currency);
        }

        return md5($amount . $merchantId . $reference . $verifyKey);
    }
}
