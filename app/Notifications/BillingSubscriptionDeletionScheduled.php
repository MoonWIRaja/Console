<?php

namespace Pterodactyl\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Pterodactyl\Models\BillingSubscription;
use Pterodactyl\Notifications\Concerns\FormatsBillingMailMessage;

class BillingSubscriptionDeletionScheduled extends Notification implements ShouldQueue
{
    use Queueable;
    use FormatsBillingMailMessage;

    public function __construct(private BillingSubscription $subscription)
    {
        $this->afterCommit();
    }

    public function via(): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->makeBillingMail(
            $notifiable,
            sprintf('Deletion Scheduled: %s', $this->subscription->server_name),
            'The subscription is still unpaid and the server is now scheduled for deletion if payment is not completed.'
        )
            ->line('Server: ' . $this->subscription->server_name)
            ->line('Deletion scheduled at: ' . $this->formatBillingDate($this->subscription->grace_delete_at, 'Unknown'));
    }
}
