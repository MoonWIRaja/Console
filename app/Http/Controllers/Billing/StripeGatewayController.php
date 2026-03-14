<?php

namespace Pterodactyl\Http\Controllers\Billing;

use Throwable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Billing\StripeWebhookService;

class StripeGatewayController extends Controller
{
    public function __construct(
        private StripeWebhookService $webhookService,
    ) {
    }

    public function webhook(Request $request): Response
    {
        try {
            $this->webhookService->handle(
                (string) $request->getContent(),
                $request->header('Stripe-Signature')
            );
        } catch (Throwable $exception) {
            report($exception);

            return response('Webhook rejected.', 422)->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        return response('OK', 200)->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    public function return(Request $request): Response
    {
        $target = '/billing';
        $sessionId = trim((string) $request->query('session_id', ''));

        if ($sessionId !== '') {
            try {
                $this->webhookService->reconcileCheckoutReturn($sessionId);
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        if ($sessionId !== '') {
            $target .= '?session_id=' . rawurlencode($sessionId);
        }

        return new Response(
            sprintf(
                '<!DOCTYPE html><html><head><meta charset="utf-8"><meta http-equiv="refresh" content="0;url=%1$s"></head><body><script>window.location.replace(%2$s);</script><p>Redirecting to billing...</p><a href=%2$s>Continue</a></body></html>',
                htmlspecialchars($target, ENT_QUOTES, 'UTF-8'),
                json_encode($target, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            ),
            200,
            ['Content-Type' => 'text/html; charset=UTF-8']
        );
    }
}
