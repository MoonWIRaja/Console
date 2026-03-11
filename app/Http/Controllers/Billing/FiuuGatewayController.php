<?php

namespace Pterodactyl\Http\Controllers\Billing;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Billing\BillingPaymentService;

class FiuuGatewayController extends Controller
{
    public function __construct(private BillingPaymentService $paymentService)
    {
    }

    public function callback(Request $request): JsonResponse
    {
        $result = $this->paymentService->handleFiuuCallback($request->all());

        return response()->json([
            'processed' => (bool) ($result['processed'] ?? false),
            'message' => $result['message'] ?? 'Callback received.',
        ], ($result['processed'] ?? false) ? 200 : 422);
    }

    public function return(Request $request): RedirectResponse
    {
        $reference = (string) ($request->input('RefNo') ?? $request->input('reference') ?? '');

        if ($reference !== '') {
            return redirect('/billing?reference=' . urlencode($reference));
        }

        return redirect('/billing');
    }
}
