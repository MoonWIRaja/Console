<?php

namespace Pterodactyl\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Pterodactyl\Models\BillingInvoice;
use Pterodactyl\Models\Ticket;
use Pterodactyl\Models\UserOAuthAccount;
use Pterodactyl\Notifications\Concerns\FormatsBillingMailMessage;
use Pterodactyl\Services\Tickets\TicketUrlService;

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
        $mail = $this->makeBillingMail(
            $notifiable,
            sprintf('Payment Action Needed: %s', $this->invoice->invoice_number),
            'A billing payment needs your attention before the service timeline is affected.'
        )
            ->line('Invoice: ' . $this->invoice->invoice_number)
            ->line('Amount due: ' . $this->formatBillingAmount($this->invoice->currency, (float) $this->invoice->grand_total))
            ->line('Due at: ' . $this->formatBillingDate($this->invoice->due_at, 'As soon as possible'))
            ->line('Reason: ' . $this->reason);

        if (config('tickets.enabled')) {
            [$label, $url] = $this->ticketActionForNotifiable($notifiable);
            if ($url !== '') {
                $mail->action($label, $url);
            }
        }

        return $mail;
    }

    private function ticketActionForNotifiable(object $notifiable): array
    {
        $existing = Ticket::query()
            ->where('billing_invoice_id', $this->invoice->id)
            ->where('category', Ticket::CATEGORY_PAYMENT)
            ->latest('id')
            ->first();

        if ($existing) {
            return ['View Payment Ticket', app(TicketUrlService::class)->clientTicketUrl($existing)];
        }

        $linked = isset($notifiable->id) && UserOAuthAccount::query()
            ->where('user_id', $notifiable->id)
            ->where('provider', 'discord')
            ->exists();

        if ($linked) {
            return ['Open Payment Ticket', app(TicketUrlService::class)->composeUrl(Ticket::CATEGORY_PAYMENT, [
                'invoiceId' => $this->invoice->id,
            ])];
        }

        return ['Link Discord & Open Ticket', route('auth.oauth.redirect', [
            'provider' => 'discord',
            'intent' => 'link',
            'return_to' => app(TicketUrlService::class)->composeUrl(Ticket::CATEGORY_PAYMENT, [
                'invoiceId' => $this->invoice->id,
            ]),
        ])];
    }
}
