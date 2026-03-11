<?php

namespace Pterodactyl\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Pterodactyl\Models\BillingSubscription;

class BillingSubscriptionSuspended extends Notification implements ShouldQueue
{
    use Queueable;

    public bool $afterCommit = true;

    public function __construct(private BillingSubscription $subscription)
    {
    }

    public function via(): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $deleteAt = $this->subscription->grace_delete_at?->copy()->setTimezone(config('app.timezone'))->format('F j, Y g:i A');

        return (new MailMessage())
            ->subject(sprintf('Server Suspended for %s', $this->subscription->server_name))
            ->greeting('Hello ' . $notifiable->username . '.')
            ->line('Your server has been suspended because the billing invoice was not paid in time.')
            ->line('Server: ' . $this->subscription->server_name)
            ->line('Deletion schedule: ' . ($deleteAt ?? 'Not scheduled'))
            ->action('Open Billing', url('/billing'));
    }
}
