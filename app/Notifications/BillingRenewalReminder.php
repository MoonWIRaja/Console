<?php

namespace Pterodactyl\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Pterodactyl\Models\BillingInvoice;

class BillingRenewalReminder extends Notification implements ShouldQueue
{
    use Queueable;

    public bool $afterCommit = true;

    public function __construct(private BillingInvoice $invoice, private int $daysUntilDue)
    {
    }

    public function via(): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subscription = $this->invoice->subscription;

        $dueAt = $this->invoice->due_at
            ? $this->invoice->due_at->copy()->setTimezone(config('app.timezone'))->format('F j, Y g:i A')
            : null;

        $deleteAt = $subscription?->grace_delete_at
            ? $subscription->grace_delete_at->copy()->setTimezone(config('app.timezone'))->format('F j, Y g:i A')
            : null;

        return (new MailMessage())
            ->subject(sprintf('Renewal Reminder for Invoice %s', $this->invoice->invoice_number))
            ->greeting('Hello ' . $notifiable->username . '.')
            ->line(sprintf(
                'Your billing renewal invoice is due in %d day%s.',
                $this->daysUntilDue,
                $this->daysUntilDue === 1 ? '' : 's'
            ))
            ->line('Invoice: ' . $this->invoice->invoice_number)
            ->line('Server: ' . ($subscription?->server_name ?? 'Unknown server'))
            ->line('Next renewal due: ' . ($dueAt ?? 'Unknown'))
            ->line('Amount due: ' . $this->invoice->currency . ' ' . number_format((float) $this->invoice->grand_total, 2))
            ->line('If payment is not completed by the due date, the server may be suspended automatically.')
            ->line('If payment is still missing after the grace period, the server may be deleted on ' . ($deleteAt ?? 'the scheduled delete time') . '.')
            ->action('Open Billing', url('/billing'));
    }
}
