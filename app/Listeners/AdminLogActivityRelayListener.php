<?php

namespace Pterodactyl\Listeners;

use Throwable;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Events\ActivityLogged;
use Pterodactyl\Services\Admin\Logs\AdminLogDiscordService;

class AdminLogActivityRelayListener
{
    public function __construct(private AdminLogDiscordService $discord)
    {
    }

    public function handle(ActivityLogged $event): void
    {
        $category = $this->categoryForEvent($event->model->event);
        if (!$category) {
            return;
        }

        try {
            $this->discord->relayActivity($event->model->loadMissing(['actor', 'subjects.subject']), $category);
        } catch (Throwable $exception) {
            Log::warning('Failed relaying admin activity log to Discord.', [
                'event' => $event->model->event,
                'category' => $category,
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    private function categoryForEvent(string $event): ?string
    {
        return match (true) {
            in_array($event, ['auth:signup', 'auth:email-verified'], true) => 'new_account',
            in_array($event, ['auth:success', 'auth:fail', 'auth:checkpoint', 'auth:sftp.fail'], true) => 'login',
            in_array($event, ['auth:password-reset-pin.requested', 'auth_failed_password_reset'], true) => 'forgot_password',
            in_array($event, ['user:account.password-changed', 'auth:password-reset-pin.completed', 'event:password-reset'], true) => 'change_password',
            $event === 'user:account.email-changed' => 'change_email',
            in_array($event, ['auth_honeyport_hit', 'auth_risk_escalated', 'auth_temp_locked', 'auth_challenge_required', 'auth:ip-blocked'], true) => 'security',
            str_starts_with($event, 'security:') => 'security',
            str_starts_with($event, 'ticket:') => 'ticket',
            default => null,
        };
    }
}
