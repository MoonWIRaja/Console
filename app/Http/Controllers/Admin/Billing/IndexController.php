<?php

namespace Pterodactyl\Http\Controllers\Admin\Billing;

use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Http\Controllers\Controller;

class IndexController extends Controller
{
    public function __construct(private AlertsMessageBag $alert)
    {
    }

    public function index(): View
    {
        return view('admin.billing.index');
    }

    public function redirect(): RedirectResponse
    {
        $this->alert->warning('Billing is temporarily disabled and marked as coming soon.')->flash();

        return redirect()->route('admin.billing');
    }
}
