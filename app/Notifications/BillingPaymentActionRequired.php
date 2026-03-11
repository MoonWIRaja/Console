<?php

namespace Pterodactyl\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Pterodactyl\Models\BillingInvoice;

class BillingPaymentActionRequired extends Notification implements ShouldQueue
{
    use Queueable;

    public bool $afterCommit = true;

    public function __construct(private BillingInvoice $invoice, private string $reason)
    {
    }

    public function via(): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $dueAt = $this->invoice->due_at?->copy()->setTimezone(config('app.timezone'))->format('F j, Y g:i A');

        return (new MailMessage())
            ->subject(sprintf('Payment Action Required for %s', $this->invoice->invoice_number))
            ->greeting('Hello ' . $notifiable->username . '.')
            ->line('There is a billing issue that needs your attention.')
            ->line('Invoice: ' . $this->invoice->invoice_number)
            ->line('Amount due: ' . $this->invoice->currency . ' ' . number_format((float) $this->invoice->grand_total, 2))
            ->line('Due at: ' . ($dueAt ?? 'As soon as possible'))
            ->line('Reason: ' . $this->reason)
            ->action('Open Billing', url('/billing'));
    }
}
