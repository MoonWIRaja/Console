<?php

namespace Pterodactyl\Http\Controllers\Billing;

use Illuminate\Http\Request;
use Pterodactyl\Models\BillingInvoice;
use Pterodactyl\Models\BillingPayment;
use Illuminate\Auth\Access\AuthorizationException;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Billing\BillingDocumentService;

class BillingDocumentController extends Controller
{
    public function __construct(private BillingDocumentService $documentService)
    {
    }

    public function invoice(Request $request, BillingInvoice $billingInvoice)
    {
        $this->authorizeInvoiceAccess($request, $billingInvoice);

        return view('billing.documents.viewer', [
            'title' => sprintf('Invoice %s', $billingInvoice->invoice_number),
            'documentTitle' => $billingInvoice->invoice_number,
            'rawUrl' => $request->hasValidSignature()
                ? $this->documentService->signedRawInvoiceUrl($billingInvoice)
                : $this->documentService->rawInvoiceUrl($billingInvoice),
        ]);
    }

    public function receipt(Request $request, BillingPayment $billingPayment)
    {
        $billingPayment->loadMissing('invoice');
        $this->authorizePaymentAccess($request, $billingPayment);

        return view('billing.documents.viewer', [
            'title' => sprintf('Receipt %s', $billingPayment->payment_number),
            'documentTitle' => $billingPayment->payment_number,
            'rawUrl' => $request->hasValidSignature()
                ? $this->documentService->signedRawReceiptUrl($billingPayment)
                : $this->documentService->rawReceiptUrl($billingPayment),
        ]);
    }

    public function invoiceRaw(Request $request, BillingInvoice $billingInvoice)
    {
        $this->authorizeInvoiceAccess($request, $billingInvoice);

        return $this->documentService->streamInvoice($billingInvoice);
    }

    public function receiptRaw(Request $request, BillingPayment $billingPayment)
    {
        $billingPayment->loadMissing('invoice');
        $this->authorizePaymentAccess($request, $billingPayment);

        return $this->documentService->streamReceipt($billingPayment);
    }

    private function authorizeInvoiceAccess(Request $request, BillingInvoice $billingInvoice): void
    {
        $user = $request->user();
        $authorized = ($user && ($user->root_admin || $user->id === $billingInvoice->user_id))
            || $request->hasValidSignature();

        throw_unless($authorized, AuthorizationException::class);
    }

    private function authorizePaymentAccess(Request $request, BillingPayment $billingPayment): void
    {
        $user = $request->user();
        $invoiceUserId = $billingPayment->invoice?->user_id;
        $authorized = ($user && ($user->root_admin || $user->id === $invoiceUserId))
            || $request->hasValidSignature();

        throw_unless($authorized, AuthorizationException::class);
    }
}
