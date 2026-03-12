<?php

namespace Pterodactyl\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Pterodactyl\Models\BillingOrder;
use Pterodactyl\Notifications\Concerns\FormatsBillingMailMessage;

class BillingProvisioningFailed extends Notification implements ShouldQueue
{
    use Queueable;
    use FormatsBillingMailMessage;

    public function __construct(private BillingOrder $order)
    {
        $this->afterCommit();
    }

    public function via(): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $reason = $this->order->provision_failure_message ?: 'The server could not be provisioned automatically.';

        return $this->makeBillingMail(
            $notifiable,
            sprintf('Provisioning Issue: %s', $this->order->server_name),
            'We received the payment, but the server was not provisioned cleanly.'
        )
            ->line('Order server name: ' . $this->order->server_name)
            ->line('Reason: ' . $reason)
            ->line('No second payment is required. The provisioning step can be retried safely.');
    }
}
