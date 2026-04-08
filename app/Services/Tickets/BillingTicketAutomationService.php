<?php

namespace Pterodactyl\Services\Tickets;

use Throwable;
use Illuminate\Support\Arr;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Ticket;
use Pterodactyl\Models\BillingPayment;
use Pterodactyl\Models\BillingInvoice;
use Pterodactyl\Models\BillingOrder;
use Pterodactyl\Services\Discord\DiscordCommunityService;

class BillingTicketAutomationService
{
    public function __construct(
        private TicketSettingsService $settings,
        private TicketService $tickets,
        private TicketDiscordService $discord,
        private DiscordCommunityService $community,
        private TicketUrlService $urls,
    ) {
    }

    public function maybeCreateManualCheckoutTicket(BillingInvoice $invoice, User $user, array &$payload = []): ?Ticket
    {
        if (!$this->settings->enabled() || !$this->settings->autoCreateOnManualCheckout()) {
            return null;
        }

        $invoice->loadMissing('order', 'subscription', 'user.oauthAccounts');
        $discordAccount = $user->oauthAccounts->first(fn ($account) => $account->provider === 'discord');
        if (!$discordAccount?->provider_id) {
            $payload['ticket_requires_discord_link'] = true;
            $payload['link_discord_url'] = route('auth.oauth.redirect', [
                'provider' => 'discord',
                'intent' => 'link',
                'return_to' => $this->urls->composeUrl(Ticket::CATEGORY_PAYMENT, ['invoiceId' => $invoice->id]),
            ]);

            return null;
        }

        try {
            $this->community->ensureGuildMembership($user);
        } catch (Throwable $exception) {
            report($exception);
        }

        $ticket = $this->tickets->create($user, [
            'category' => Ticket::CATEGORY_PAYMENT,
            'subject' => sprintf('Payment help for invoice %s', $invoice->invoice_number),
            'billing_invoice_id' => $invoice->id,
            'billing_order_id' => $invoice->billing_order_id,
            'billing_subscription_id' => $invoice->subscription_id,
            'body' => sprintf(
                'This support ticket was created automatically for invoice %s (%s %.2f).',
                $invoice->invoice_number,
                $invoice->currency,
                (float) $invoice->grand_total
            ),
        ], [
            'source' => Ticket::SOURCE_CHECKOUT,
            'status' => Ticket::STATUS_WAITING_FOR_USER,
            'created_via' => 'manual_checkout',
            'checkout_context' => [
                'invoice_number' => $invoice->invoice_number,
                'invoice_status' => $invoice->status,
            ],
        ]);

        $payload['ticket_auto_created'] = true;
        $payload['ticket'] = $ticket;

        try {
            $ticket = $this->discord->ensureTicketThread($ticket->fresh(['user.oauthAccounts', 'invoice', 'order', 'subscription']));
            $ticket = $this->discord->syncPendingMessages($ticket);
            $payload['ticket'] = $ticket;
        } catch (Throwable $exception) {
            report($exception);
            $payload['ticket'] = $ticket->fresh();
            $payload['ticket_warning'] = $exception->getMessage();
        }

        return $ticket;
    }

    public function markPaymentSatisfied(BillingInvoice $invoice): void
    {
        if (!$this->settings->enabled()) {
            return;
        }

        $ticket = Ticket::query()
            ->where('billing_invoice_id', $invoice->id)
            ->where('category', Ticket::CATEGORY_PAYMENT)
            ->latest('id')
            ->first();

        if (!$ticket) {
            return;
        }

        $ticket = $this->tickets->postSystemMessage(
            $ticket,
            sprintf('Payment has been confirmed for invoice %s.', $invoice->invoice_number),
            ['event' => 'billing.payment_confirmed']
        );

        $this->relayTicketMessages($ticket);

        if ($this->settings->resolveOnPaid()) {
            $this->tickets->updateStatus($ticket->fresh(), Ticket::STATUS_RESOLVED);
        }
    }

    public function markRefundCompleted(BillingPayment $payment): void
    {
        if (!$this->settings->enabled()) {
            return;
        }

        $ticket = Ticket::query()
            ->where('billing_payment_id', $payment->id)
            ->where('category', Ticket::CATEGORY_REFUND)
            ->latest('id')
            ->first();

        if (!$ticket) {
            return;
        }

        $ticket = $this->tickets->postSystemMessage(
            $ticket,
            sprintf('Refund has been completed for payment %s.', $payment->payment_number),
            ['event' => 'billing.refund_completed']
        );

        $this->relayTicketMessages($ticket);
        $this->tickets->updateStatus($ticket->fresh(), Ticket::STATUS_RESOLVED);
    }

    public function markOrderRejected(BillingOrder $order): void
    {
        if (!$this->settings->enabled() || !$order->billing_invoice_id) {
            return;
        }

        $ticket = Ticket::query()
            ->where('billing_invoice_id', $order->billing_invoice_id)
            ->where('category', Ticket::CATEGORY_PAYMENT)
            ->latest('id')
            ->first();

        if (!$ticket) {
            return;
        }

        $ticket = $this->tickets->postSystemMessage(
            $ticket,
            sprintf('Order #%d was rejected by billing staff.', $order->id),
            ['event' => 'billing.order_rejected']
        );

        $this->relayTicketMessages($ticket);
        $this->tickets->updateStatus($ticket->fresh(), Ticket::STATUS_RESOLVED);
    }

    public function markProvisionFailed(BillingOrder $order): void
    {
        if (!$this->settings->enabled() || !$order->billing_invoice_id) {
            return;
        }

        $ticket = Ticket::query()
            ->where('billing_invoice_id', $order->billing_invoice_id)
            ->where('category', Ticket::CATEGORY_PAYMENT)
            ->latest('id')
            ->first();

        if (!$ticket) {
            return;
        }

        $ticket = $this->tickets->postSystemMessage(
            $ticket,
            sprintf('Provisioning failed for order #%d: %s', $order->id, $order->provision_failure_message ?: 'Unknown error'),
            ['event' => 'billing.provision_failed']
        );

        $this->relayTicketMessages($ticket);
        $this->tickets->updateStatus($ticket->fresh(), Ticket::STATUS_WAITING_FOR_STAFF);
    }

    private function relayTicketMessages(Ticket $ticket): void
    {
        try {
            $this->discord->syncPendingMessages($ticket);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
