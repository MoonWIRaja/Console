<?php

namespace Pterodactyl\Http\Controllers\Admin\Billing;

use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Models\BillingTaxRule;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Http\Requests\Admin\Billing\BillingTaxRuleRequest;

class TaxRuleController extends Controller
{
    public function __construct(private AlertsMessageBag $alert)
    {
    }

    public function index(): View
    {
        return view('admin.billing.tax-rules', [
            'rules' => BillingTaxRule::query()->orderBy('priority')->get(),
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
}
