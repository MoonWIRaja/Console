<?php

namespace Pterodactyl\Http\Controllers\Admin\Billing;

use Throwable;
use Illuminate\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Models\BillingOrder;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Billing\BillingOrderProvisionService;

class ProvisionFailureController extends Controller
{
    private const STATUSES = [
        BillingOrder::STATUS_PROVISION_FAILED,
        BillingOrder::STATUS_PROVISIONING,
        BillingOrder::STATUS_QUEUED_PROVISION,
        BillingOrder::STATUS_FAILED,
    ];

    public function __construct(
        private AlertsMessageBag $alert,
        private BillingOrderProvisionService $provisionService,
    ) {
    }

    public function index(Request $request): View
    {
        $status = $this->selectedStatus($request) ?? BillingOrder::STATUS_PROVISION_FAILED;

        return view('admin.billing.provision-failures', [
            'orders' => BillingOrder::query()
                ->with(['user', 'invoice'])
                ->where('status', $status)
                ->latest()
                ->paginate(50)
                ->appends($request->except('page')),
            'provisionStatusOptions' => $this->statusOptions(self::STATUSES),
            'selectedProvisionStatus' => $status,
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
