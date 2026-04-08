<?php

namespace Pterodactyl\Http\Controllers\Admin\OAuth;

use Throwable;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Contracts\Console\Kernel;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Http\Requests\Admin\OAuth\UpdateOAuthSettingsRequest;
use Pterodactyl\Services\Admin\Settings\AdminSettingsStoreService;

class OAuthController extends Controller
{
    public function __construct(
        private AlertsMessageBag $alert,
        private Kernel $kernel,
        private AdminSettingsStoreService $settings,
    ) {
    }

    public function index(): View
    {
        return view('admin.oauth.index');
    }

    public function update(UpdateOAuthSettingsRequest $request): RedirectResponse
    {
        $this->settings->save($request->normalize());

        try {
            $this->kernel->call('queue:restart');
        } catch (Throwable $exception) {
            report($exception);
            $this->alert->warning('OAuth settings were saved, but queue restart could not be triggered automatically.')->flash();

            return redirect()->route('admin.oauth');
        }

        $this->alert->success('OAuth settings have been updated successfully.')->flash();

        return redirect()->route('admin.oauth');
    }
}
