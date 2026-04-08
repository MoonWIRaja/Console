<?php

namespace Pterodactyl\Http\Controllers\Billing;

use Throwable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Billing\BillingPaymentService;

class BclGatewayController extends Controller
{
    public function __construct(private BillingPaymentService $paymentService)
    {
    }

    public function webhook(Request $request): Response
    {
        try {
            $result = $this->paymentService->handleBclWebhook($request->all());
        } catch (Throwable $exception) {
            report($exception);

            return response('Webhook rejected.', 422)->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        return response(
            $result['message'] ?? 'OK',
            ($result['processed'] ?? false) ? 200 : 422
        )->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
