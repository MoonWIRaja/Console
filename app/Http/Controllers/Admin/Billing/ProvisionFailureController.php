<?php

namespace Pterodactyl\Http\Controllers\Admin\Billing;

use Throwable;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Models\BillingOrder;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Billing\BillingOrderProvisionService;

class ProvisionFailureController extends Controller
{
    public function __construct(
        private AlertsMessageBag $alert,
        private BillingOrderProvisionService $provisionService,
    ) {
    }

    public function index(): View
    {
        return view('admin.billing.provision-failures', [
            'orders' => BillingOrder::query()
                ->with(['user', 'invoice'])
                ->where('status', BillingOrder::STATUS_PROVISION_FAILED)
                ->latest()
                ->paginate(50),
        ]);
    }

    public function retry(BillingOrder $billingOrder): RedirectResponse
    {
        try {
            $this->provisionService->handle($billingOrder, request()->user());
        } catch (Throwable $exception) {
            report($exception);
            $this->alert->danger($exception->getMessage())->flash();

            return redirect()->route('admin.billing.provision-failures');
        }

        $this->alert->success('Provision retry dispatched successfully.')->flash();

        return redirect()->route('admin.billing.provision-failures');
    }
}
