<?php

namespace Pterodactyl\Notifications;

use Illuminate\Bus\Queueable;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;
use Pterodactyl\Models\BillingOrder;
use Pterodactyl\Notifications\Concerns\FormatsBillingMailMessage;

class BillingProvisioningFailed extends Notification implements ShouldQueue
{
    use Queueable;
    use FormatsBillingMailMessage;

    public function __construct(private BillingOrder $order)
    {
        $this->afterCommit();
    }

    public function via(): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $reason = $this->order->provision_failure_message ?: 'The server could not be provisioned automatically.';
        $invoice = $this->order->invoice;
        $payment = $invoice?->payments?->sortByDesc('id')->first();
        $invoiceUrl = $invoice
            ? URL::temporarySignedRoute(
                'billing.documents.invoices.show',
                CarbonImmutable::now()->addDays(30),
                ['billingInvoice' => $invoice->id]
            )
            : null;
        $receiptUrl = $payment
            ? URL::temporarySignedRoute(
                'billing.documents.payments.receipt',
                CarbonImmutable::now()->addDays(30),
                ['billingPayment' => $payment->id]
            )
            : null;

        $mail = $this->makeBillingMail(
            $notifiable,
            sprintf('Provisioning Issue: %s', $this->order->server_name),
            'We received the payment, but the server was not provisioned cleanly.'
        )
            ->line('Order server name: ' . $this->order->server_name)
            ->line('Reason: ' . $reason)
            ->line('No second payment is required. The provisioning step can be retried safely.');

        if ($invoice) {
            $mail->line('Invoice: ' . $invoice->invoice_number);
        }

        if ($payment) {
            $mail->line('Payment: ' . $payment->payment_number);
        }

        if ($receiptUrl) {
            $mail
                ->line('Your payment record is still safe and no second payment is required.')
                ->action('Open Receipt', $receiptUrl);
        } elseif ($invoiceUrl) {
            $mail
                ->line('The invoice and payment records remain available in your billing workspace.')
                ->action('Open Invoice', $invoiceUrl);
        }

        return $mail;
    }
}
