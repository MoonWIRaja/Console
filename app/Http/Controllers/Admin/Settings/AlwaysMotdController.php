<?php

namespace Pterodactyl\Http\Controllers\Admin\Settings;

use Throwable;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\AlwaysMotd\AlwaysMotdConfigService;
use Pterodactyl\Services\AlwaysMotd\LiveMinecraftMotdSyncService;
use Pterodactyl\Http\Requests\Admin\Settings\UpdateAlwaysMotdSettingsRequest;

class AlwaysMotdController extends Controller
{
    public function __construct(
        private AlertsMessageBag $alert,
        private AlwaysMotdConfigService $configService,
        private LiveMinecraftMotdSyncService $liveSyncService,
    ) {
    }

    public function index(): View
    {
        return view('admin.settings.always-motd', $this->configService->getViewData());
    }

    public function update(UpdateAlwaysMotdSettingsRequest $request): RedirectResponse
    {
        try {
            $this->configService->save($request->validated(), $request->file('motd_icon'));

            $messages = ['Minecraft MOTD settings have been updated.'];

            if ($request->boolean('motd.sync_panel_logo')) {
                $messages[] = 'The current panel logo was copied into the Minecraft MOTD icon.';
            } elseif ($request->hasFile('motd_icon')) {
                $messages[] = 'The uploaded icon replaced the current Minecraft MOTD icon.';
            }

            $summary = $this->liveSyncService->syncMatchingServers();
            $messages[] = sprintf(
                'Matching Minecraft servers synced immediately: %d synced, %d skipped, %d failed.',
                $summary['synced'],
                $summary['skipped'],
                $summary['failed']
            );

            if ($summary['synced'] > 0) {
                $messages[] = 'Players will see the new MOTD after the server is running. Restart any server that was already online so Minecraft reloads the updated MOTD and icon.';
            }
        } catch (Throwable $exception) {
            report($exception);
            $this->alert->danger($exception->getMessage())->flash();

            return redirect()->route('admin.always-motd')->withInput();
        }

        $this->alert->success(implode(' ', $messages))->flash();

        return redirect()->route('admin.always-motd');
    }
}
