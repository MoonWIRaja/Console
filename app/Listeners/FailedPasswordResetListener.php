<?php

namespace Pterodactyl\Listeners;

use Pterodactyl\Facades\Activity;
use Pterodactyl\Events\Auth\FailedPasswordReset;

class FailedPasswordResetListener
{
    public function handle(FailedPasswordReset $event): void
    {
        Activity::event('auth_failed_password_reset')
            ->property('ip', $event->ip)
            ->property('email', $event->email)
            ->log();
    }
}
