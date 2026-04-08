<?php

namespace Pterodactyl\Http\Controllers\Admin\Settings;

use Throwable;
use Illuminate\View\View;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Prologue\Alerts\AlertsMessageBag;
use Illuminate\Contracts\Console\Kernel;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Traits\Helpers\AvailableLanguages;
use Pterodactyl\Services\Helpers\SoftwareVersionService;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;
use Pterodactyl\Http\Requests\Admin\Settings\BaseSettingsFormRequest;

class IndexController extends Controller
{
    use AvailableLanguages;

    /**
     * IndexController constructor.
     */
    public function __construct(
        private AlertsMessageBag $alert,
        private Kernel $kernel,
        private SettingsRepositoryInterface $settings,
        private SoftwareVersionService $versionService,
    ) {
    }

    /**
     * Render the UI for basic Panel settings.
     */
    public function index(): View
    {
        return view('admin.settings.index', [
            'version' => $this->versionService,
            'languages' => $this->getAvailableLanguages(true),
        ]);
    }

    /**
     * Handle settings update.
     *
     * @throws \Pterodactyl\Exceptions\Model\DataValidationException
     * @throws \Pterodactyl\Exceptions\Repository\RecordNotFoundException
     */
    public function update(BaseSettingsFormRequest $request): RedirectResponse
    {
        foreach ($request->normalize(['app:name', 'pterodactyl:auth:2fa_required', 'app:locale']) as $key => $value) {
            $this->settings->set('settings::' . $key, $value);
        }

        if ($request->hasFile('app_logo')) {
            $this->replaceBrandingAsset($request->file('app_logo'), 'settings::app:logo', 'panel-logo');
        }

        if ($request->hasFile('app_auth_logo')) {
            $this->replaceBrandingAsset($request->file('app_auth_logo'), 'settings::app:auth_logo', 'auth-brand-logo');
        }

        try {
            $this->kernel->call('queue:restart');
        } catch (Throwable $exception) {
            report($exception);
            $this->alert->warning('Panel settings were saved, but queue restart could not be triggered automatically.')->flash();

            return redirect()->route('admin.settings');
        }

        $this->alert->success('Panel settings have been updated successfully and the queue worker was restarted to apply these changes.')->flash();

        return redirect()->route('admin.settings');
    }

    private function replaceBrandingAsset(UploadedFile $file, string $settingsKey, string $filePrefix): void
    {
        $oldLogo = $this->settings->get($settingsKey, null);
        if (!empty($oldLogo) && str_starts_with($oldLogo, 'storage/branding/')) {
            Storage::disk('public')->delete(Str::after($oldLogo, 'storage/'));
        }

        $path = $file->storeAs(
            'branding',
            $filePrefix . '-' . Str::random(24) . '.' . $file->extension(),
            'public'
        );

        $this->settings->set($settingsKey, 'storage/' . $path);
    }
}
