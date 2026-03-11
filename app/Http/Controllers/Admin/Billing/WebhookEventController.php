<?php

namespace Pterodactyl\Http\Controllers\Admin\Billing;

use Throwable;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Models\BillingGatewayEvent;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Billing\BillingWebhookReplayService;

class WebhookEventController extends Controller
{
    public function __construct(
        private AlertsMessageBag $alert,
        private BillingWebhookReplayService $replayService,
    ) {
    }

    public function index(): View
    {
        return view('admin.billing.webhook-events', [
            'events' => BillingGatewayEvent::query()->latest()->paginate(100),
        ]);
    }

    public function replay(BillingGatewayEvent $billingGatewayEvent): RedirectResponse
    {
        try {
            $result = $this->replayService->replay($billingGatewayEvent);
            $message = $result['message'] ?? 'Webhook replay completed.';
            $this->alert->success($message)->flash();
        } catch (Throwable $exception) {
            report($exception);
            $this->alert->danger($exception->getMessage())->flash();
        }

        return redirect()->route('admin.billing.webhook-events');
    }
}
