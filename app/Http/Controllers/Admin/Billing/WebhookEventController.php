<?php

namespace Pterodactyl\Http\Controllers\Admin\Billing;

use Throwable;
use Illuminate\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Models\BillingGatewayEvent;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Billing\BillingWebhookReplayService;

class WebhookEventController extends Controller
{
    private const STATUSES = [
        BillingGatewayEvent::STATUS_RECEIVED,
        BillingGatewayEvent::STATUS_PROCESSED,
        BillingGatewayEvent::STATUS_FAILED,
    ];

    public function __construct(
        private AlertsMessageBag $alert,
        private BillingWebhookReplayService $replayService,
    ) {
    }

    public function index(Request $request): View
    {
        $status = $this->selectedStatus($request);
        $query = BillingGatewayEvent::query()->latest();

        if ($status !== null) {
            $query->where('status', $status);
        }

        return view('admin.billing.webhook-events', [
            'events' => $query->paginate(50)->appends($request->except('page')),
            'webhookStatusOptions' => $this->statusOptions(self::STATUSES),
            'selectedWebhookStatus' => $status,
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

    private function selectedStatus(Request $request): ?string
    {
        $status = (string) $request->query('status', '');

        return in_array($status, self::STATUSES, true) ? $status : null;
    }

    private function statusOptions(array $statuses): array
    {
        return array_reduce($statuses, function (array $options, string $status): array {
            $options[$status] = ucwords(str_replace('_', ' ', $status));

            return $options;
        }, []);
    }
}
