<?php

namespace Pterodactyl\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Pterodactyl\Models\BillingInvoice;
use Pterodactyl\Models\BillingPayment;
use Pterodactyl\Notifications\Concerns\FormatsBillingMailMessage;

class BillingPaymentReceipt extends Notification implements ShouldQueue
{
    use Queueable;
    use FormatsBillingMailMessage;

    public function __construct(private BillingInvoice $invoice, private BillingPayment $payment)
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
            sprintf('Payment Confirmed: %s', $this->invoice->invoice_number),
            'We verified your billing payment successfully.'
        )
            ->line('Invoice: ' . $this->invoice->invoice_number)
            ->line('Payment: ' . $this->payment->payment_number)
            ->line('Amount paid: ' . $this->formatBillingAmount($this->payment->currency, (float) $this->payment->amount))
            ->line('Paid at: ' . $this->formatBillingDate($this->payment->paid_at, 'Just now'));
    }
}
