<?php

namespace Pterodactyl\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Pterodactyl\Models\BillingInvoice;
use Pterodactyl\Notifications\Concerns\FormatsBillingMailMessage;

class BillingPaymentActionRequired extends Notification implements ShouldQueue
{
    use Queueable;
    use FormatsBillingMailMessage;

    public function __construct(private BillingInvoice $invoice, private string $reason)
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
            sprintf('Payment Action Needed: %s', $this->invoice->invoice_number),
            'A billing payment needs your attention before the service timeline is affected.'
        )
            ->line('Invoice: ' . $this->invoice->invoice_number)
            ->line('Amount due: ' . $this->formatBillingAmount($this->invoice->currency, (float) $this->invoice->grand_total))
            ->line('Due at: ' . $this->formatBillingDate($this->invoice->due_at, 'As soon as possible'))
            ->line('Reason: ' . $this->reason);
    }
}
