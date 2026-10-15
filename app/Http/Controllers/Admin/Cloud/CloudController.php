<?php

namespace Pterodactyl\Http\Controllers\Admin\Cloud;

use Throwable;
use Illuminate\View\View;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Models\Cloud\CloudFile;
use Pterodactyl\Models\Cloud\CloudAccount;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Cloud\GoogleDriveClient;
use Pterodactyl\Services\Admin\Settings\AdminSettingsStoreService;

class CloudController extends Controller
{
    public function __construct(
        private AlertsMessageBag $alert,
        private GoogleDriveClient $drive,
        private AdminSettingsStoreService $settings,
    ) {
    }

    public function index(Request $request): View
    {
        $tab = in_array($request->query('tab'), ['pool', 'settings', 'usage', 'setup'], true) ? $request->query('tab') : 'pool';

        return view('admin.cloud.index', [
            'tab' => $tab,
            'accounts' => CloudAccount::query()->withCount('files')->orderBy('priority')->orderBy('id')->get(),
            'googleConfigured' => filled(config('services.google.client_id')) && filled(config('services.google.client_secret')),
            'redirectUri' => GoogleDriveClient::redirectUri(),
            'stats' => [
                'files' => CloudFile::query()->count(),
                'bytes' => (int) CloudFile::query()->sum('size'),
                'users' => CloudFile::query()->distinct('user_id')->count('user_id'),
                'shared' => CloudFile::query()->whereNotNull('share_token')->count(),
            ],
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'cloud:enabled' => 'required|in:true,false',
            'cloud:quota_gb' => 'required|numeric|min:0.1|max:10000',
        ]);

        $this->settings->save($data);
        $this->alert->success('Cloud storage settings saved.')->flash();

        return redirect()->route('admin.cloud', ['tab' => 'settings']);
    }

    public function connect(Request $request): RedirectResponse
    {
        if (!filled(config('services.google.client_id')) || !filled(config('services.google.client_secret'))) {
            $this->alert->danger('Configure the Google OAuth client in /admin/oauth first.')->flash();

            return redirect()->route('admin.cloud');
        }

        $state = Str::random(40);
        $request->session()->put('cloud_google_state', $state);

        return redirect()->away($this->drive->authUrl($state));
    }

    public function callback(Request $request): RedirectResponse
    {
        $expected = (string) $request->session()->pull('cloud_google_state', '');
        if ($expected === '' || !hash_equals($expected, (string) $request->query('state', ''))) {
            $this->alert->danger('The Google login expired or did not match. Try connecting again.')->flash();

            return redirect()->route('admin.cloud');
        }

        if ($request->filled('error') || !$request->filled('code')) {
            $this->alert->danger('Google login was cancelled: ' . $request->query('error', 'no code returned'))->flash();

            return redirect()->route('admin.cloud');
        }

        try {
            $tokens = $this->drive->exchangeCode((string) $request->query('code'));
            $account = CloudAccount::query()->firstOrNew(['email' => $tokens['email']]);
            if (!$account->exists) {
                $account->priority = (int) CloudAccount::query()->max('priority') + 1;
            }
            $account->fill([
                'access_token' => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'],
                'token_expires_at' => now()->addSeconds($tokens['expires_in']),
                'enabled' => true,
                'last_error' => null,
            ])->save();
            $this->drive->refreshQuota($account);
        } catch (Throwable $exception) {
            $this->alert->danger('Could not connect Google Drive: ' . $exception->getMessage())->flash();

            return redirect()->route('admin.cloud');
        }

        $this->alert->success('Connected ' . $account->email . '.')->flash();

        return redirect()->route('admin.cloud');
    }

    public function reorder(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate(['order' => 'required|array|max:100', 'order.*' => 'integer']);

        foreach (array_values($data['order']) as $position => $id) {
            CloudAccount::query()->where('id', $id)->update(['priority' => $position]);
        }

        return new \Illuminate\Http\JsonResponse(['ok' => true]);
    }

    public function toggle(CloudAccount $account): RedirectResponse
    {
        $account->update(['enabled' => !$account->enabled]);
        $this->alert->success($account->email . ($account->enabled ? ' enabled.' : ' disabled for new uploads (existing files stay available).'))->flash();

        return redirect()->route('admin.cloud');
    }

    public function refresh(CloudAccount $account): RedirectResponse
    {
        try {
            $this->drive->refreshQuota($account);
            $this->alert->success('Storage usage refreshed for ' . $account->email . '.')->flash();
        } catch (Throwable $exception) {
            $this->alert->danger($exception->getMessage())->flash();
        }

        return redirect()->route('admin.cloud');
    }

    public function destroy(CloudAccount $account): RedirectResponse
    {
        if ($account->files()->exists()) {
            $this->alert->danger('This account still stores ' . $account->files()->count() . ' user files. Disable it instead so no new uploads go there.')->flash();

            return redirect()->route('admin.cloud');
        }

        $account->delete();
        $this->alert->success('Disconnected ' . $account->email . '.')->flash();

        return redirect()->route('admin.cloud');
    }
}
