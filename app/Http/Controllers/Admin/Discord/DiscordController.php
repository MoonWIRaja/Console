<?php

namespace Pterodactyl\Http\Controllers\Admin\Discord;

use Throwable;
use Illuminate\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Contracts\Console\Kernel;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Http\Requests\Admin\Discord\UpdateDiscordSettingsRequest;
use Pterodactyl\Services\Admin\Settings\AdminSettingsStoreService;

class DiscordController extends Controller
{
    public function __construct(
        private AlertsMessageBag $alert,
        private Kernel $kernel,
        private AdminSettingsStoreService $settings,
    ) {
    }

    public function index(Request $request): View
    {
        $activeTab = in_array((string) $request->query('tab', 'bot'), ['bot', 'community'], true)
            ? (string) $request->query('tab', 'bot')
            : 'bot';

        return view('admin.discord.index', [
            'activeTab' => $activeTab,
        ]);
    }

    public function update(UpdateDiscordSettingsRequest $request): RedirectResponse
    {
        $this->settings->save($request->normalize());

        try {
            $this->kernel->call('queue:restart');
        } catch (Throwable $exception) {
            report($exception);
            $this->alert->warning('Discord settings were saved, but queue restart could not be triggered automatically.')->flash();

            return redirect()->route('admin.discord', ['tab' => $request->input('tab', 'bot')]);
        }

        $this->alert->success('Discord settings have been updated successfully.')->flash();

        return redirect()->route('admin.discord', ['tab' => $request->input('tab', 'bot')]);
    }
}
