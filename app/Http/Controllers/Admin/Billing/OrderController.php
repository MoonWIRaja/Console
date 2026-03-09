<?php

namespace Pterodactyl\Http\Controllers\Admin\Billing;

use Throwable;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Models\BillingOrder;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Billing\BillingOrderProvisionService;
use Pterodactyl\Http\Requests\Admin\Billing\BillingOrderDecisionRequest;

class OrderController extends Controller
{
    public function __construct(
        private AlertsMessageBag $alert,
        private BillingOrderProvisionService $provisionService,
    ) {
    }

    public function view(BillingOrder $billingOrder): View
    {
        return view('admin.billing.order', [
            'order' => $billingOrder->load(['user', 'node', 'server', 'approver', 'gameProfile.egg']),
        ]);
    }

    public function approve(BillingOrderDecisionRequest $request, BillingOrder $billingOrder): RedirectResponse
    {
        try {
            $this->provisionService->handle($billingOrder, $request->user());
        } catch (Throwable $exception) {
            report($exception);
            $this->alert->danger($exception->getMessage())->flash();

            return redirect()->route('admin.billing.orders.view', $billingOrder->id);
        }

        $this->alert->success('The billing order was approved and the server provisioning request has been submitted.')->flash();

        return redirect()->route('admin.billing.orders.view', $billingOrder->id);
    }

    public function reject(BillingOrderDecisionRequest $request, BillingOrder $billingOrder): RedirectResponse
    {
        try {
            $this->provisionService->reject($billingOrder, $request->user(), $request->input('admin_notes'));
        } catch (Throwable $exception) {
            report($exception);
            $this->alert->danger($exception->getMessage())->flash();

            return redirect()->route('admin.billing.orders.view', $billingOrder->id);
        }

        $this->alert->success('The billing order has been rejected.')->flash();

        return redirect()->route('admin.billing.orders.view', $billingOrder->id);
    }
}
