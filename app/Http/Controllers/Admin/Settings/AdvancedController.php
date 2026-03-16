<?php

namespace Pterodactyl\Http\Controllers\Admin\Settings;

use Throwable;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Illuminate\Contracts\Console\Kernel;
use Pterodactyl\Http\Controllers\Controller;
use Illuminate\Contracts\Encryption\Encrypter;
use Pterodactyl\Providers\SettingsServiceProvider;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;
use Pterodactyl\Http\Requests\Admin\Settings\AdvancedSettingsFormRequest;

class AdvancedController extends Controller
{
    /**
     * AdvancedController constructor.
     */
    public function __construct(
        private AlertsMessageBag $alert,
        private Encrypter $encrypter,
        private Kernel $kernel,
        private SettingsRepositoryInterface $settings,
    ) {
    }

    /**
     * Redirect advanced settings requests to the combined settings page.
     */
    public function index(): RedirectResponse
    {
        return redirect()->route('admin.settings');
    }

    /**
     * @throws \Pterodactyl\Exceptions\Model\DataValidationException
     * @throws \Pterodactyl\Exceptions\Repository\RecordNotFoundException
     */
    public function update(AdvancedSettingsFormRequest $request): RedirectResponse
    {
        foreach ($request->normalize() as $key => $value) {
            if (in_array($key, SettingsServiceProvider::getEncryptedKeys(), true)) {
                if ($value === '!e') {
                    $value = '';
                } elseif (!is_string($value) || trim($value) === '') {
                    continue;
                } elseif (!empty($value)) {
                    $value = $this->encrypter->encrypt($value);
                }
            }

            $this->settings->set('settings::' . $key, $value);
        }

        try {
            $this->kernel->call('queue:restart');
        } catch (Throwable $exception) {
            report($exception);
            $this->alert->warning('Advanced settings were saved, but queue restart could not be triggered automatically.')->flash();

            return redirect()->route('admin.settings');
        }

        $this->alert->success('Advanced settings have been updated successfully and the queue worker was restarted to apply these changes.')->flash();

        return redirect()->route('admin.settings');
    }
}
