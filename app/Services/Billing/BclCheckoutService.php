<?php

namespace Pterodactyl\Services\Billing;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Pterodactyl\Models\BillingInvoice;
use Pterodactyl\Models\BillingPaymentAttempt;
use Pterodactyl\Exceptions\DisplayException;

class BclCheckoutService
{
    public const PROVIDER = 'bcl';

    public function isEnabled(): bool
    {
        return (bool) config('billing.bcl.enabled');
    }

    public function buildCheckout(BillingInvoice $invoice, BillingPaymentAttempt $attempt): array
    {
        if (!$this->isEnabled()) {
            throw new DisplayException('BCL hosted checkout is not enabled yet.');
        }

        $invoice->loadMissing('user');

        $snapshot = $invoice->billing_profile_snapshot ?? [];
        $payerName = trim((string) ($snapshot['legal_name'] ?: $invoice->user?->name ?: ''));
        $payerEmail = trim((string) ($snapshot['email'] ?: $invoice->user?->email ?: ''));
        $payerPhone = preg_replace('/[^\d+]/', '', (string) ($snapshot['phone'] ?: ''));
        $amount = $this->formatAmount((float) $invoice->grand_total);
        $currency = strtoupper(trim((string) ($invoice->currency ?: config('billing.currency', 'MYR'))));
        $description = Str::limit(sprintf(
            '%s invoice %s',
            str_replace('_', ' ', strtoupper($invoice->type)),
            $invoice->invoice_number
        ), 120, '');

        if ($payerName === '') {
            throw new DisplayException('Billing profile is missing a full name. Update your billing details in /account before starting checkout.');
        }

        if ($payerEmail === '') {
            throw new DisplayException('Billing profile is missing an email address. Update your billing details in /account before starting checkout.');
        }

        if ($payerPhone === '') {
            throw new DisplayException('Billing profile is missing a phone number. Update your billing details in /account before starting checkout.');
        }

        if ($currency === '') {
            throw new DisplayException('Invoice currency is missing. Configure billing currency before starting BCL checkout.');
        }

        $payload = [
            'name' => $payerName,
            'email' => $payerEmail,
            'phone' => $payerPhone,
            'amount' => $amount,
            'currency' => $currency,
            'description' => $description,
            'invoice_number' => $invoice->invoice_number,
            'panel_checkout_reference' => $attempt->checkout_reference,
            'panel_invoice_id' => (string) $invoice->id,
            'panel_invoice_number' => $invoice->invoice_number,
            'panel_amount' => $amount,
            'panel_currency' => $currency,
            'panel_signature' => $this->signPayload($invoice, $attempt->checkout_reference, $amount, $currency),
        ];

        return [
            'provider' => self::PROVIDER,
            'method' => 'GET',
            'url' => $this->formUrl(),
            'payload' => $payload,
            'request_payload' => $payload,
        ];
    }

    public function resumeCheckout(BillingPaymentAttempt $attempt, array $payload): array
    {
        return [
            'provider' => self::PROVIDER,
            'method' => 'GET',
            'url' => $this->formUrl(),
            'payload' => $this->normalizePayload($payload),
            'request_payload' => $this->normalizePayload($payload),
        ];
    }

    public function normalizeWebhook(array $payload): array
    {
        $data = $this->ensureArray($payload['data'] ?? []);
        $mainData = $this->ensureArray(Arr::get($data, 'main_data', []));
        $customFields = $this->normalizeCustomFields(Arr::get($data, 'custom_fields_data', []));
        $sources = [$payload, $data, $mainData, $customFields];
        $panelSources = [$customFields, $payload, $data, $mainData];

        $eventType = $this->normalizeEventType($this->firstValue($sources, [
            'event',
            'event_type',
            'type',
            'action',
        ]));

        return [
            'event_type' => $eventType,
            'provider_status' => $this->normalizeStatus($this->firstValue($sources, [
                'payment_status',
                'status',
                'payment_state',
                'state',
            ]), $eventType),
            'record_id' => $this->firstValue($sources, ['record_id', 'payment_id', 'transaction_id']),
            'transaction_id' => $this->firstValue($sources, ['transaction_id', 'record_id', 'payment_id']),
            'reference' => $this->firstValue($panelSources, [
                'panel_checkout_reference',
                'checkout_reference',
                'reference',
                'ref',
                'order_id',
            ]),
            'invoice_id' => $this->toInteger($this->firstValue($panelSources, ['panel_invoice_id', 'invoice_id'])),
            'invoice_number' => $this->firstValue($panelSources, ['panel_invoice_number', 'invoice_number']),
            'amount' => $this->normalizeAmount($this->firstValue($panelSources, [
                'panel_amount',
                'amount',
                'payment_amount',
                'paid_amount',
                'total',
                'grand_total',
            ])),
            'currency' => $this->normalizeCurrency($this->firstValue($panelSources, [
                'panel_currency',
                'currency',
                'currency_code',
            ])),
            'signature' => $this->firstValue($panelSources, ['panel_signature', 'signature']),
            'payment_method' => $this->firstValue($sources, [
                'payment_method',
                'payment_channel',
                'channel',
                'method',
            ]),
            'invoice_pdf_url' => $this->firstValue($sources, ['invoice_pdf_url', 'receipt_url', 'download_url']),
            'receipt_url' => $this->firstValue($sources, ['receipt_url', 'payment_url', 'invoice_url']),
            'main_data' => $mainData,
            'custom_fields' => $customFields,
            'raw' => $payload,
        ];
    }

    public function verifySignature(BillingInvoice $invoice, string $reference, ?string $amount, ?string $currency, ?string $signature): bool
    {
        if ($reference === '' || blank($signature) || blank($amount) || blank($currency)) {
            return false;
        }

        $expected = $this->signPayload($invoice, $reference, (string) $amount, (string) $currency);

        return hash_equals($expected, trim((string) $signature));
    }

    public function amountMatches(BillingInvoice $invoice, ?string $amount): bool
    {
        if (blank($amount)) {
            return false;
        }

        return $this->formatAmount((float) $invoice->grand_total) === $this->normalizeAmount($amount);
    }

    public function currencyMatches(BillingInvoice $invoice, ?string $currency): bool
    {
        if (blank($currency)) {
            return false;
        }

        return strtoupper((string) $invoice->currency) === $this->normalizeCurrency($currency);
    }

    private function formUrl(): string
    {
        $url = trim((string) config('billing.bcl.form_url', ''));
        if ($url === '') {
            throw new DisplayException('BCL form URL has not been configured.');
        }

        return $url;
    }

    private function signPayload(BillingInvoice $invoice, string $reference, string $amount, string $currency): string
    {
        $secret = (string) config('app.key', '');
        if (str_starts_with($secret, 'base64:')) {
            $decoded = base64_decode(substr($secret, 7), true);
            if ($decoded !== false) {
                $secret = $decoded;
            }
        }

        return hash_hmac('sha256', implode('|', [
            $invoice->id,
            $invoice->invoice_number,
            $reference,
            $this->normalizeAmount($amount),
            $this->normalizeCurrency($currency),
        ]), $secret);
    }

    private function normalizePayload(array $payload): array
    {
        $normalized = [];

        foreach ($payload as $key => $value) {
            if (is_array($value) || is_object($value)) {
                continue;
            }

            $key = trim((string) $key);
            if ($key === '') {
                continue;
            }

            $normalized[$key] = trim((string) $value);
        }

        return $normalized;
    }

    private function normalizeCustomFields(mixed $fields): array
    {
        if (!is_array($fields)) {
            return [];
        }

        $normalized = [];

        foreach ($fields as $key => $value) {
            if (is_array($value)) {
                $name = $this->normalizeKey((string) (
                    Arr::get($value, 'name')
                    ?? Arr::get($value, 'field_name')
                    ?? Arr::get($value, 'slug')
                    ?? Arr::get($value, 'label')
                    ?? $key
                ));
                $fieldValue = Arr::get($value, 'value', Arr::get($value, 'field_value', Arr::get($value, 'answer')));
            } else {
                $name = $this->normalizeKey((string) $key);
                $fieldValue = $value;
            }

            if ($name === '' || is_array($fieldValue) || is_object($fieldValue)) {
                continue;
            }

            $normalized[$name] = trim((string) $fieldValue);
        }

        return $normalized;
    }

    private function ensureArray(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    private function firstValue(array $sources, array $keys): ?string
    {
        foreach ($sources as $source) {
            if (!is_array($source)) {
                continue;
            }

            foreach ($keys as $key) {
                if (array_key_exists($key, $source) && !is_array($source[$key]) && !is_object($source[$key])) {
                    $value = trim((string) $source[$key]);

                    if ($value !== '') {
                        return $value;
                    }
                }

                $normalizedKey = $this->normalizeKey($key);
                if ($normalizedKey !== $key && array_key_exists($normalizedKey, $source) && !is_array($source[$normalizedKey]) && !is_object($source[$normalizedKey])) {
                    $value = trim((string) $source[$normalizedKey]);

                    if ($value !== '') {
                        return $value;
                    }
                }
            }
        }

        return null;
    }

    private function normalizeEventType(?string $value): string
    {
        $normalized = strtolower(trim((string) $value));

        return str_replace('_', '-', preg_replace('/\s+/', '-', $normalized) ?: $normalized);
    }

    private function normalizeStatus(?string $value, string $fallback): string
    {
        $normalized = strtolower(trim((string) ($value ?: $fallback)));

        return str_replace('_', '-', preg_replace('/\s+/', '-', $normalized) ?: $normalized);
    }

    private function normalizeAmount(mixed $value): ?string
    {
        if (is_null($value)) {
            return null;
        }

        if (is_numeric($value)) {
            return $this->formatAmount((float) $value);
        }

        $normalized = preg_replace('/[^0-9.\-]/', '', str_replace(',', '', trim((string) $value)));
        if ($normalized === '' || !is_numeric($normalized)) {
            return null;
        }

        return $this->formatAmount((float) $normalized);
    }

    private function normalizeCurrency(?string $value): ?string
    {
        $normalized = strtoupper(trim((string) $value));

        return $normalized === '' ? null : $normalized;
    }

    private function formatAmount(float $value): string
    {
        return number_format($value, 2, '.', '');
    }

    private function toInteger(?string $value): ?int
    {
        if (blank($value) || !is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }

    private function normalizeKey(string $key): string
    {
        $key = trim($key);
        if ($key === '') {
            return '';
        }

        return Str::of($key)
            ->replace('-', '_')
            ->replace(' ', '_')
            ->snake()
            ->toString();
    }
}
