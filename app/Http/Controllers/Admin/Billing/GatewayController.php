<?php

namespace Pterodactyl\Http\Controllers\Admin\Billing;

use Throwable;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Encryption\Encrypter;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Providers\SettingsServiceProvider;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;
use Pterodactyl\Http\Requests\Admin\Billing\BillingGatewaySettingsRequest;

class GatewayController extends Controller
{
    public function __construct(
        private AlertsMessageBag $alert,
        private Kernel $kernel,
        private Encrypter $encrypter,
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

            if (in_array($key, SettingsServiceProvider::getEncryptedKeys(), true) && !empty($stored)) {
                $stored = $this->encrypter->encrypt($stored);
            }

            $this->settings->set('settings::' . $key, $stored);
        }

        try {
            $this->kernel->call('queue:restart');
        } catch (Throwable $exception) {
            report($exception);
            $this->alert->warning('Billing gateway settings were saved, but queue restart could not be triggered automatically.')->flash();

            return redirect()->route('admin.billing.gateway');
        }

        $this->alert->success('Billing gateway settings have been updated.')->flash();

        return redirect()->route('admin.billing.gateway');
    }
}
