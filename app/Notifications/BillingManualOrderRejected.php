<?php

namespace Pterodactyl\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Pterodactyl\Models\BillingOrder;
use Pterodactyl\Notifications\Concerns\FormatsBillingMailMessage;

class BillingManualOrderRejected extends Notification implements ShouldQueue
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
        return $this->makeBillingMail(
            $notifiable,
            sprintf('Billing Order Rejected: %s', $this->order->invoice?->invoice_number ?? ('#' . $this->order->id)),
            'Your manual billing order was reviewed and could not be approved.'
        )
            ->line('Order: #' . $this->order->id)
            ->line('Invoice: ' . ($this->order->invoice?->invoice_number ?? 'Not assigned'))
            ->line('Reason: ' . ($this->order->admin_notes ?: 'Please contact billing admin for the exact reason.'));
    }
}
