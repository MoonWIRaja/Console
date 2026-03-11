<?php

namespace Pterodactyl\Http\Controllers\Admin\Billing;

use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Contracts\Console\Kernel;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;
use Pterodactyl\Http\Requests\Admin\Billing\BillingGatewaySettingsRequest;

class GatewayController extends Controller
{
    public function __construct(
        private AlertsMessageBag $alert,
        private Kernel $kernel,
        private SettingsRepositoryInterface $settings,
    ) {
    }

    public function index(): View
    {
        return view('admin.billing.gateway');
    }

    public function update(BillingGatewaySettingsRequest $request): RedirectResponse
    {
        foreach ($request->normalize() as $key => $value) {
            if (is_bool($value)) {
                $stored = $value ? 'true' : 'false';
            } elseif (is_array($value)) {
                $stored = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            } else {
                $stored = is_null($value) ? null : (string) $value;
            }

            $this->settings->set('settings::' . $key, $stored);
        }

        $this->kernel->call('queue:restart');
        $this->alert->success('Billing gateway settings have been updated.')->flash();

        return redirect()->route('admin.billing.gateway');
    }
}
