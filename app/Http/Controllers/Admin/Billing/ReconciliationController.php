<?php

namespace Pterodactyl\Http\Controllers\Admin\Billing;

use Illuminate\View\View;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Billing\BillingSettlementReconciliationService;

class ReconciliationController extends Controller
{
    public function __construct(private BillingSettlementReconciliationService $reconciliationService)
    {
    }

    public function index(): View
    {
        return view('admin.billing.reconciliation', $this->reconciliationService->report());
    }
}
