<?php

namespace Pterodactyl\Services\Billing;

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
        if (!$this->isEnabled()) {
            throw new DisplayException('Fiuu checkout is not enabled yet.');
        }

        $merchantId = (string) config('billing.fiuu.merchant_id');
        if ($merchantId === '') {
            throw new DisplayException('Fiuu Merchant ID has not been configured.');
        }

        $method = trim((string) collect(config('billing.fiuu.enabled_methods', []))->first());
        $baseUrl = rtrim((string) (
            config('billing.fiuu.sandbox')
                ? config('billing.fiuu.checkout_urls.sandbox')
                : config('billing.fiuu.checkout_urls.production')
        ), '/');

        $url = $baseUrl . '/' . $merchantId;
        if ($method !== '') {
            $url .= '/' . $method;
        }

        $snapshot = $invoice->billing_profile_snapshot ?? [];
        $amount = number_format((float) $invoice->grand_total, 2, '.', '');
        $reference = $attempt->checkout_reference;
        $verifyKey = (string) config('billing.fiuu.verify_key', '');

        $payload = [
            'MerchantID' => $merchantId,
            'RefNo' => $reference,
            'Amount' => $amount,
            'Currency' => $invoice->currency,
            'ProdDesc' => Str::limit(sprintf('%s invoice %s', strtoupper($invoice->type), $invoice->invoice_number), 120, ''),
            'UserName' => (string) ($snapshot['legal_name'] ?: $invoice->user?->name ?: 'Panel User'),
            'UserEmail' => (string) ($snapshot['email'] ?: $invoice->user?->email),
            'UserContact' => (string) ($snapshot['phone'] ?: ''),
            'Remark' => sprintf('Invoice %s', $invoice->invoice_number),
            'Lang' => 'en',
            'ReturnURL' => (string) config('billing.fiuu.return_url'),
            'CallbackURL' => (string) config('billing.fiuu.callback_url'),
        ];

        if ($verifyKey !== '') {
            $payload['vcode'] = md5($amount . $merchantId . $reference . $verifyKey);
        }

        return [
            'provider' => self::PROVIDER,
            'method' => 'POST',
            'url' => $url,
            'payload' => $payload,
        ];
    }
}
