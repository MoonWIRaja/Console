<?php

namespace Pterodactyl\Http\Controllers\Billing;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\BillingInvoice;
use Pterodactyl\Models\BillingPaymentAttempt;
use Pterodactyl\Services\Billing\BillingPaymentService;
use Pterodactyl\Services\Billing\FiuuCheckoutService;

class FiuuGatewayController extends Controller
{
    public function __construct(
        private BillingPaymentService $paymentService,
        private FiuuCheckoutService $checkoutService,
    )
    {
    }

    public function checkout(string $checkoutReference): Response
    {
        $billingPaymentAttempt = BillingPaymentAttempt::query()
            ->with('invoice')
            ->where('checkout_reference', $checkoutReference)
            ->latest('id')
            ->firstOrFail();

        $billingPaymentAttempt->loadMissing('invoice');

        if (
            $billingPaymentAttempt->provider !== FiuuCheckoutService::PROVIDER
            || !is_array($billingPaymentAttempt->raw_request_payload)
            || $billingPaymentAttempt->raw_request_payload === []
        ) {
            return $this->redirectToBilling($billingPaymentAttempt->checkout_reference);
        }

        $invoice = $billingPaymentAttempt->invoice;
        if ($invoice && in_array($invoice->status, [
            BillingInvoice::STATUS_PAID,
            BillingInvoice::STATUS_REFUNDED,
            BillingInvoice::STATUS_VOID,
        ], true)) {
            return $this->redirectToBilling($billingPaymentAttempt->checkout_reference);
        }

        $html = view('billing.fiuu-checkout', [
            'reference' => $billingPaymentAttempt->checkout_reference,
            'action' => $this->checkoutService->resolveMerchantUrl(),
            'payload' => $billingPaymentAttempt->raw_request_payload,
        ])->render();

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => 'Thu, 01 Jan 1970 00:00:00 GMT',
        ]);
    }

    public function callback(Request $request): Response
    {
        try {
            $result = $this->paymentService->handleFiuuCallback($request->all());
        } catch (\Throwable $exception) {
            report($exception);

            return response('Callback received and queued for retry.', 202)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        $nbcb = trim((string) $request->input('nbcb', ''));

        if (($result['processed'] ?? false) && $nbcb === '1') {
            return response('CBTOKEN:MPSTATOK', 200)->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        return response(
            $result['message'] ?? 'Callback received.',
            ($result['processed'] ?? false) ? 200 : 422
        )->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    public function return(Request $request): Response
    {
        $reference = (string) ($request->input('RefNo') ?? $request->input('reference') ?? $request->input('orderid') ?? '');

        try {
            if ($this->looksLikeGatewayPayload($request)) {
                $this->paymentService->handleFiuuCallback($request->all(), true);
            }
        } catch (\Throwable $exception) {
            report($exception);
        }

        return $this->redirectToBilling($reference);
    }

    private function looksLikeGatewayPayload(Request $request): bool
    {
        return $request->hasAny(['orderid', 'RefNo', 'tranID', 'TranID', 'status', 'Status']);
    }

    private function redirectToBilling(string $reference = ''): Response
    {
        $target = '/billing';
        if ($reference !== '') {
            $target .= '?reference=' . rawurlencode($reference);
        }

        return new Response(
            sprintf(
                '<!DOCTYPE html><html><head><meta charset="utf-8"><meta http-equiv="refresh" content="0;url=%1$s"></head><body><script>window.location.replace(%2$s);</script><p>Redirecting to billing...</p><a href=%2$s>Continue</a></body></html>',
                htmlspecialchars($target, ENT_QUOTES, 'UTF-8'),
                json_encode($target, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            ),
            200,
            [
                'Content-Type' => 'text/html; charset=UTF-8',
            ]
        );
    }
}
