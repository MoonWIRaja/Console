<?php

namespace Pterodactyl\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Pterodactyl\Models\BillingInvoice;

class BillingInvoiceIssued extends Notification implements ShouldQueue
{
    use Queueable;

    public bool $afterCommit = true;

    public function __construct(private BillingInvoice $invoice)
    {
    }

    public function via(): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $typeLabel = str_replace('_', ' ', strtoupper($this->invoice->type));
        $dueAt = $this->invoice->due_at?->copy()->setTimezone(config('app.timezone'))->format('F j, Y g:i A');

        return (new MailMessage())
            ->subject(sprintf('Invoice %s Issued', $this->invoice->invoice_number))
            ->greeting('Hello ' . $notifiable->username . '.')
            ->line(sprintf('A new %s invoice has been issued to your account.', $typeLabel))
            ->line('Invoice: ' . $this->invoice->invoice_number)
            ->line('Amount due: ' . $this->invoice->currency . ' ' . number_format((float) $this->invoice->grand_total, 2))
            ->line('Due at: ' . ($dueAt ?? 'Pay as soon as possible'))
            ->action('Open Billing', url('/billing'));
    }
}
