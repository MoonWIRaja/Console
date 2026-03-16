<?php

namespace Pterodactyl\Notifications;

use Illuminate\Bus\Queueable;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;
use Pterodactyl\Models\BillingInvoice;
use Pterodactyl\Models\Ticket;
use Pterodactyl\Models\UserOAuthAccount;
use Pterodactyl\Services\Billing\BillingPaymentService;
use Pterodactyl\Notifications\Concerns\FormatsBillingMailMessage;
use Pterodactyl\Services\Tickets\TicketUrlService;

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
        $isManual = $this->invoice->provider === BillingPaymentService::MANUAL_PROVIDER;

        if ($isManual) {
            $invoiceUrl = URL::temporarySignedRoute(
                'billing.documents.invoices.show',
                CarbonImmutable::now()->addDays(30),
                ['billingInvoice' => $this->invoice->id]
            );
            [$actionLabel, $actionUrl] = $this->ticketActionForNotifiable($notifiable);

            $mail = $this->makeBillingMail(
                $notifiable,
                sprintf('Manual Payment Required: %s', $this->invoice->invoice_number),
                sprintf('Your %s invoice has been created and is waiting for manual payment approval.', $typeLabel)
            )
                ->line('Invoice: ' . $this->invoice->invoice_number)
                ->line('Amount due: ' . $this->formatBillingAmount($this->invoice->currency, (float) $this->invoice->grand_total))
                ->line('Due at: ' . $this->formatBillingDate($this->invoice->due_at, 'Pay as soon as possible'))
                ->line(config('tickets.enabled')
                    ? 'Open your support ticket and include the invoice number above when sending payment proof.'
                    : 'Send your payment proof through your usual support channel and include the invoice number above.')
                ->line('A billing administrator will review the payment manually after it is received.');

            if (config('tickets.enabled') && $actionUrl !== '') {
                $mail->action($actionLabel, $actionUrl);
            } else {
                $mail->action('Open Invoice', $invoiceUrl);
            }

            return $mail;
        }

        return $this->makeBillingMail(
            $notifiable,
            sprintf('Invoice Ready: %s', $this->invoice->invoice_number),
            sprintf('A %s invoice is ready in your billing workspace.', $typeLabel)
        )
            ->line('Invoice: ' . $this->invoice->invoice_number)
            ->line('Amount due: ' . $this->formatBillingAmount($this->invoice->currency, (float) $this->invoice->grand_total))
            ->line('Due at: ' . $this->formatBillingDate($this->invoice->due_at, 'Pay as soon as possible'))
            ->line('You can review the invoice details and complete the next step from your billing workspace.');
    }

    private function ticketActionForNotifiable(object $notifiable): array
    {
        $existing = Ticket::query()
            ->where('billing_invoice_id', $this->invoice->id)
            ->where('category', Ticket::CATEGORY_PAYMENT)
            ->latest('id')
            ->first();

        if ($existing) {
            return ['Open Payment Ticket', app(TicketUrlService::class)->clientTicketUrl($existing)];
        }

        $composeUrl = app(TicketUrlService::class)->composeUrl(Ticket::CATEGORY_PAYMENT, [
            'invoiceId' => $this->invoice->id,
        ]);

        $linked = isset($notifiable->id) && UserOAuthAccount::query()
            ->where('user_id', $notifiable->id)
            ->where('provider', 'discord')
            ->exists();

        if ($linked) {
            return ['Open Payment Ticket', $composeUrl];
        }

        return ['Link Discord & Open Ticket', route('auth.oauth.redirect', [
            'provider' => 'discord',
            'intent' => 'link',
            'return_to' => '/tickets?compose=payment&invoiceId=' . $this->invoice->id,
        ])];
    }
}
