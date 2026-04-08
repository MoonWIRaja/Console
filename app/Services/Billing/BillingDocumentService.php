<?php

namespace Pterodactyl\Services\Billing;

use Carbon\CarbonImmutable;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Pterodactyl\Models\BillingInvoice;
use Pterodactyl\Models\BillingPayment;

class BillingDocumentService
{
    private const SIGNED_LINK_TTL_DAYS = 30;

    public function invoiceUrl(BillingInvoice $invoice): ?string
    {
        if (!Route::has('billing.documents.invoices.show')) {
            return $invoice->invoice_pdf_url;
        }

        return route('billing.documents.invoices.show', ['billingInvoice' => $invoice->id]);
    }

    public function signedInvoiceUrl(BillingInvoice $invoice, ?CarbonImmutable $expiresAt = null): ?string
    {
        if (!Route::has('billing.documents.invoices.show')) {
            return $invoice->invoice_pdf_url;
        }

        return URL::temporarySignedRoute(
            'billing.documents.invoices.show',
            $expiresAt ?: CarbonImmutable::now()->addDays(self::SIGNED_LINK_TTL_DAYS),
            ['billingInvoice' => $invoice->id]
        );
    }

    public function rawInvoiceUrl(BillingInvoice $invoice): ?string
    {
        if (!Route::has('billing.documents.invoices.raw')) {
            return $this->invoiceUrl($invoice);
        }

        return route('billing.documents.invoices.raw', ['billingInvoice' => $invoice->id]);
    }

    public function signedRawInvoiceUrl(BillingInvoice $invoice, ?CarbonImmutable $expiresAt = null): ?string
    {
        if (!Route::has('billing.documents.invoices.raw')) {
            return $this->signedInvoiceUrl($invoice, $expiresAt);
        }

        return URL::temporarySignedRoute(
            'billing.documents.invoices.raw',
            $expiresAt ?: CarbonImmutable::now()->addDays(self::SIGNED_LINK_TTL_DAYS),
            ['billingInvoice' => $invoice->id]
        );
    }

    public function receiptUrl(BillingPayment $payment): ?string
    {
        if (!Route::has('billing.documents.payments.receipt')) {
            return null;
        }

        return route('billing.documents.payments.receipt', ['billingPayment' => $payment->id]);
    }

    public function signedReceiptUrl(BillingPayment $payment, ?CarbonImmutable $expiresAt = null): ?string
    {
        if (!Route::has('billing.documents.payments.receipt')) {
            return null;
        }

        return URL::temporarySignedRoute(
            'billing.documents.payments.receipt',
            $expiresAt ?: CarbonImmutable::now()->addDays(self::SIGNED_LINK_TTL_DAYS),
            ['billingPayment' => $payment->id]
        );
    }

    public function rawReceiptUrl(BillingPayment $payment): ?string
    {
        if (!Route::has('billing.documents.payments.receipt.raw')) {
            return $this->receiptUrl($payment);
        }

        return route('billing.documents.payments.receipt.raw', ['billingPayment' => $payment->id]);
    }

    public function signedRawReceiptUrl(BillingPayment $payment, ?CarbonImmutable $expiresAt = null): ?string
    {
        if (!Route::has('billing.documents.payments.receipt.raw')) {
            return $this->signedReceiptUrl($payment, $expiresAt);
        }

        return URL::temporarySignedRoute(
            'billing.documents.payments.receipt.raw',
            $expiresAt ?: CarbonImmutable::now()->addDays(self::SIGNED_LINK_TTL_DAYS),
            ['billingPayment' => $payment->id]
        );
    }

    public function syncInvoiceUrl(BillingInvoice $invoice): BillingInvoice
    {
        $url = $this->invoiceUrl($invoice);
        if (!$url || $invoice->invoice_pdf_url === $url) {
            return $invoice;
        }

        $invoice->forceFill([
            'invoice_pdf_url' => $url,
        ])->saveOrFail();

        return $invoice->fresh();
    }

    public function streamInvoice(BillingInvoice $invoice)
    {
        $invoice->loadMissing(['items', 'user', 'order', 'subscription', 'payments']);
        $pdf = Pdf::loadView('billing.documents.invoice', [
            'invoice' => $invoice,
            'statusLabel' => strtoupper($invoice->paid_at ? 'PAID' : 'UNPAID'),
            'snapshot' => $invoice->billing_profile_snapshot ?? [],
            'branding' => $this->documentBranding(),
        ])->setPaper('a4');

        return $pdf->stream(sprintf('%s.pdf', $invoice->invoice_number));
    }

    public function streamReceipt(BillingPayment $payment)
    {
        $payment->loadMissing(['invoice.items', 'invoice.user', 'invoice.order', 'invoice.subscription']);
        $pdf = Pdf::loadView('billing.documents.receipt', [
            'payment' => $payment,
            'invoice' => $payment->invoice,
            'snapshot' => $payment->invoice->billing_profile_snapshot ?? [],
            'branding' => $this->documentBranding(),
        ])->setPaper('a4');

        return $pdf->stream(sprintf('%s.pdf', $payment->payment_number));
    }

    private function documentBranding(): array
    {
        return [
            'name' => (string) config('app.name', 'Pterodactyl'),
            'logo_data_uri' => $this->resolveLogoDataUri(),
            'website' => (string) config('app.url', ''),
        ];
    }

    private function resolveLogoDataUri(): ?string
    {
        $candidates = array_filter([
            config('app.logo'),
            'favicons/android-chrome-192x192.png',
            'favicons/apple-touch-icon.png',
        ]);

        foreach ($candidates as $candidate) {
            $path = public_path(ltrim((string) $candidate, '/'));
            if (!File::exists($path) || !File::isFile($path)) {
                continue;
            }

            $mime = File::mimeType($path) ?: 'application/octet-stream';
            $contents = File::get($path);

            return sprintf('data:%s;base64,%s', $mime, base64_encode($contents));
        }

        return null;
    }
}
