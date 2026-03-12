<?php

namespace Pterodactyl\Http\Controllers\Billing;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Billing\BillingPaymentService;

class FiuuGatewayController extends Controller
{
    public function __construct(private BillingPaymentService $paymentService)
    {
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
