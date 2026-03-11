<?php

namespace Pterodactyl\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Pterodactyl\Models\BillingOrder;

class BillingProvisioningFailed extends Notification implements ShouldQueue
{
    use Queueable;

    public bool $afterCommit = true;

    public function __construct(private BillingOrder $order)
    {
    }

    public function via(): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $reason = $this->order->provision_failure_message ?: 'The server could not be provisioned automatically.';

        return (new MailMessage())
            ->subject(sprintf('Provisioning Issue for %s', $this->order->server_name))
            ->greeting('Hello ' . $notifiable->username . '.')
            ->line('We received your payment, but the server provisioning step did not complete successfully.')
            ->line('Order server name: ' . $this->order->server_name)
            ->line('Reason: ' . $reason)
            ->line('Our team can retry provisioning without requiring another payment.')
            ->action('Open Billing', url('/billing'));
    }
}
