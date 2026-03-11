<?php

namespace Pterodactyl\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Pterodactyl\Models\BillingInvoice;
use Pterodactyl\Models\BillingPayment;

class BillingPaymentReceipt extends Notification implements ShouldQueue
{
    use Queueable;

    public bool $afterCommit = true;

    public function __construct(private BillingInvoice $invoice, private BillingPayment $payment)
    {
    }

    public function via(): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $paidAt = $this->payment->paid_at?->copy()->setTimezone(config('app.timezone'))->format('F j, Y g:i A');

        return (new MailMessage())
            ->subject(sprintf('Payment Received for %s', $this->invoice->invoice_number))
            ->greeting('Hello ' . $notifiable->username . '.')
            ->line('We have verified your payment successfully.')
            ->line('Invoice: ' . $this->invoice->invoice_number)
            ->line('Payment: ' . $this->payment->payment_number)
            ->line('Amount paid: ' . $this->payment->currency . ' ' . number_format((float) $this->payment->amount, 2))
            ->line('Paid at: ' . ($paidAt ?? 'Just now'))
            ->action('Open Billing', url('/billing'));
    }
}
