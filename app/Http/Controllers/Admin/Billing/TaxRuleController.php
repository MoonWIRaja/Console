<?php

namespace Pterodactyl\Http\Controllers\Admin\Billing;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Models\BillingTaxRule;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Http\Requests\Admin\Billing\BillingTaxRuleRequest;

class TaxRuleController extends Controller
{
    private const ACTIVE_FILTERS = [
        'active' => 'Active',
        'inactive' => 'Inactive',
    ];

    public function __construct(private AlertsMessageBag $alert)
    {
    }

    public function index(Request $request): View
    {
        $status = $this->selectedStatus($request);
        $query = BillingTaxRule::query()->orderBy('priority');

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        return view('admin.billing.tax-rules', [
            'rules' => $query->paginate(50)->appends($request->except('page')),
            'ruleStatusOptions' => self::ACTIVE_FILTERS,
            'selectedRuleStatus' => $status,
        ]);
    }

    public function store(BillingTaxRuleRequest $request): RedirectResponse
    {
        BillingTaxRule::query()->create($request->normalize());
        $this->alert->success('Tax rule created successfully.')->flash();

        return redirect()->route('admin.billing.tax-rules');
    }

    public function update(BillingTaxRuleRequest $request, BillingTaxRule $billingTaxRule): RedirectResponse
    {
        $billingTaxRule->fill($request->normalize())->saveOrFail();
        $this->alert->success('Tax rule updated successfully.')->flash();

        return redirect()->route('admin.billing.tax-rules');
    }

    private function selectedStatus(Request $request): ?string
    {
        $status = (string) $request->query('status', '');

        return array_key_exists($status, self::ACTIVE_FILTERS) ? $status : null;
    }
}
