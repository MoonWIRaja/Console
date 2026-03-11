<?php

namespace Pterodactyl\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Pterodactyl\Models\BillingRefund;

class BillingRefundCompleted extends Notification implements ShouldQueue
{
    use Queueable;

    public bool $afterCommit = true;

    public function __construct(private BillingRefund $refund)
    {
    }

    public function via(): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $completedAt = $this->refund->completed_at?->copy()->setTimezone(config('app.timezone'))->format('F j, Y g:i A');

        return (new MailMessage())
            ->subject(sprintf('Refund Completed for %s', $this->refund->refund_number))
            ->greeting('Hello ' . $notifiable->username . '.')
            ->line('Your refund has been completed successfully.')
            ->line('Refund: ' . $this->refund->refund_number)
            ->line('Amount: ' . $this->refund->payment->currency . ' ' . number_format((float) $this->refund->amount, 2))
            ->line('Completed at: ' . ($completedAt ?? 'Just now'))
            ->action('Open Billing', url('/billing'));
    }
}
