<?php

namespace Pterodactyl\Http\Controllers\Admin\Tickets;

use Throwable;
use Illuminate\Support\Arr;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Encryption\Encrypter;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Providers\SettingsServiceProvider;
use Pterodactyl\Services\Tickets\TicketDiscordService;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;
use Pterodactyl\Http\Requests\Admin\Tickets\UpdateTicketSettingsRequest;

class SettingsController extends Controller
{
    public function __construct(
        private AlertsMessageBag $alert,
        private Kernel $kernel,
        private Encrypter $encrypter,
        private SettingsRepositoryInterface $settings,
        private TicketDiscordService $discord,
    ) {
    }

    public function index(): View
    {
        return view('admin.tickets.settings');
    }

    public function update(UpdateTicketSettingsRequest $request): RedirectResponse
    {
        $warnings = [];
        $notices = [];
        $normalized = $this->autoProvisionDerivedSettings($request->normalize(), $warnings, $notices);

        foreach ($normalized as $key => $value) {
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
            config()->set(str_replace(':', '.', $key), $value);
        }

        foreach ($warnings as $warning) {
            $this->alert->warning($warning)->flash();
        }

        try {
            $this->kernel->call('queue:restart');
        } catch (Throwable $exception) {
            report($exception);
            $this->alert->warning('Ticket settings were saved, but queue restart could not be triggered automatically.')->flash();

            return redirect()->route('admin.tickets.settings');
        }

        $message = 'Ticket settings have been updated.';
        if ($notices !== []) {
            $message .= ' ' . implode(' ', $notices);
        }

        $this->alert->success($message)->flash();

        return redirect()->route('admin.tickets.settings');
    }

    public function syncLauncher(): RedirectResponse
    {
        try {
            $payload = $this->discord->syncLauncherMessage();
            if (!empty($payload['id'])) {
                $this->settings->set('settings::tickets:discord:launcher_message_id', (string) $payload['id']);
            }
            $this->alert->success('Discord launcher message synchronized. The gateway sidecar handles launcher interactions.')->flash();
        } catch (Throwable $exception) {
            report($exception);
            $this->alert->danger($exception->getMessage())->flash();
        }

        return redirect()->route('admin.tickets.settings');
    }

    private function autoProvisionDerivedSettings(array $values, array &$warnings = [], array &$notices = []): array
    {
        $warnings = [];
        $notices = [];

        $currentParentChannelId = trim((string) config('tickets.discord.active_parent_channel_id', ''));
        $nextParentChannelId = trim((string) ($values['tickets:discord:active_parent_channel_id'] ?? $currentParentChannelId));
        $currentWebhookId = trim((string) config('tickets.discord.relay_webhook_id', ''));
        $currentWebhookToken = trim((string) config('tickets.discord.relay_webhook_token', ''));
        $nextWebhookId = trim((string) ($values['tickets:discord:relay_webhook_id'] ?? $currentWebhookId));
        $nextWebhookToken = trim((string) ($values['tickets:discord:relay_webhook_token'] ?? $currentWebhookToken));
        $botToken = trim((string) ($values['services:discord:bot_token'] ?? config('services.discord.bot_token', '')));
        $currentSecret = trim((string) config('tickets.bridge.shared_secret', ''));
        $nextSecret = trim((string) ($values['tickets:bridge:shared_secret'] ?? $currentSecret));

        if ($nextSecret === '') {
            $values['tickets:bridge:shared_secret'] = $this->discord->generateBridgeSharedSecret();
            $notices[] = 'Bridge shared secret was generated automatically.';
        }

        $parentChannelChanged = $nextParentChannelId !== '' && $nextParentChannelId !== $currentParentChannelId;
        $relayMissing = $nextWebhookId === '' || $nextWebhookToken === '';

        if ($nextParentChannelId !== '' && $botToken !== '' && ($relayMissing || $parentChannelChanged)) {
            try {
                $webhook = $this->discord->createRelayWebhook($nextParentChannelId, $botToken);
                $values['tickets:discord:relay_webhook_id'] = Arr::get($webhook, 'id');
                $values['tickets:discord:relay_webhook_token'] = Arr::get($webhook, 'token');
                $notices[] = $parentChannelChanged
                    ? 'Relay webhook was regenerated automatically for the new active parent channel.'
                    : 'Relay webhook was created automatically.';
            } catch (Throwable $exception) {
                report($exception);
                $warnings[] = 'Relay webhook could not be created automatically. Check the bot token, active parent channel ID, and Discord Manage Webhooks permission.';
            }
        }

        return $values;
    }
}
