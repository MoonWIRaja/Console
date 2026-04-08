<?php

namespace Pterodactyl\Notifications;

use Illuminate\Bus\Queueable;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;
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
        $receiptUrl = URL::temporarySignedRoute(
            'billing.documents.payments.receipt',
            CarbonImmutable::now()->addDays(30),
            ['billingPayment' => $this->payment->id]
        );
        $mail = $this->makeBillingMail(
            $notifiable,
            sprintf('Payment Confirmed: %s', $this->invoice->invoice_number),
            'We verified your billing payment successfully.'
        )
            ->line('Invoice: ' . $this->invoice->invoice_number)
            ->line('Payment: ' . $this->payment->payment_number)
            ->line('Amount paid: ' . $this->formatBillingAmount($this->payment->currency, (float) $this->payment->amount))
            ->line('Paid at: ' . $this->formatBillingDate($this->payment->paid_at, 'Just now'))
            ->line('A copy of the invoice and receipt is now available in your billing workspace.')
            ->action('Open Receipt', $receiptUrl);

        if ($this->invoice->order?->server && $this->invoice->type === BillingInvoice::TYPE_NEW_SERVER) {
            $mail->line('Your new server is now provisioned and ready to access from the panel.');
        } elseif ($this->invoice->type === BillingInvoice::TYPE_RENEWAL) {
            $mail->line('Your subscription renewal has been applied.');
        } elseif ($this->invoice->type === BillingInvoice::TYPE_UPGRADE) {
            $mail->line('Your upgrade has been applied to the active server.');
        }

        return $mail;
    }
}
