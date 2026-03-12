<?php

namespace Pterodactyl\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Pterodactyl\Models\BillingInvoice;
use Pterodactyl\Notifications\Concerns\FormatsBillingMailMessage;

class BillingInvoiceIssued extends Notification implements ShouldQueue
{
    use Queueable;
    use FormatsBillingMailMessage;

    public function __construct(private BillingInvoice $invoice)
    {
        $this->afterCommit();
    }

    public function via(): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $typeLabel = str_replace('_', ' ', strtoupper($this->invoice->type));

        return $this->makeBillingMail(
            $notifiable,
            sprintf('Invoice Ready: %s', $this->invoice->invoice_number),
            sprintf('A %s invoice is ready in your billing workspace.', $typeLabel)
        )
            ->line('Invoice: ' . $this->invoice->invoice_number)
            ->line('Amount due: ' . $this->formatBillingAmount($this->invoice->currency, (float) $this->invoice->grand_total))
            ->line('Due at: ' . $this->formatBillingDate($this->invoice->due_at, 'Pay as soon as possible'));
    }
}
