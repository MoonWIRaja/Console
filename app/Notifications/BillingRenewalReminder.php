<?php

namespace Pterodactyl\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Pterodactyl\Models\BillingInvoice;
use Pterodactyl\Notifications\Concerns\FormatsBillingMailMessage;

class BillingRenewalReminder extends Notification implements ShouldQueue
{
    use Queueable;
    use FormatsBillingMailMessage;

    public function __construct(private BillingInvoice $invoice, private int $daysUntilDue)
    {
        $this->afterCommit();
    }

    public function via(): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subscription = $this->invoice->subscription;

        return $this->makeBillingMail(
            $notifiable,
            sprintf('Renewal Reminder: %s', $this->invoice->invoice_number),
            sprintf(
                'Your billing renewal invoice is due in %d day%s.',
                $this->daysUntilDue,
                $this->daysUntilDue === 1 ? '' : 's'
            )
        )
            ->line('Invoice: ' . $this->invoice->invoice_number)
            ->line('Server: ' . ($subscription?->server_name ?? 'Unknown server'))
            ->line('Next renewal due: ' . $this->formatBillingDate($this->invoice->due_at, 'Unknown'))
            ->line('Amount due: ' . $this->formatBillingAmount($this->invoice->currency, (float) $this->invoice->grand_total))
            ->line('If payment is not completed by the due date, the server may be suspended automatically.')
            ->line('If payment is still missing after the grace period, the server may be deleted on ' . $this->formatBillingDate($subscription?->grace_delete_at, 'the scheduled delete time') . '.');
    }
}
