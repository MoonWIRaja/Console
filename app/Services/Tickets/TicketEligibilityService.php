<?php

namespace Pterodactyl\Services\Tickets;

use Pterodactyl\Models\User;
use Pterodactyl\Models\Ticket;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\BillingPayment;
use Pterodactyl\Models\BillingInvoice;

class TicketEligibilityService
{
    public function paymentEligibles(User $user): array
    {
        return BillingInvoice::query()
            ->with(['order', 'subscription'])
            ->where('user_id', $user->id)
            ->whereIn('status', [
                BillingInvoice::STATUS_DRAFT,
                BillingInvoice::STATUS_OPEN,
                BillingInvoice::STATUS_PROCESSING,
                BillingInvoice::STATUS_FAILED,
            ])
            ->latest('id')
            ->get()
            ->map(function (BillingInvoice $invoice) {
                $existing = $this->activePaymentTicketForInvoice($invoice);

                return [
                    'type' => Ticket::CATEGORY_PAYMENT,
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'order_id' => $invoice->billing_order_id,
                    'subscription_id' => $invoice->subscription_id,
                    'subject' => sprintf('Payment help for invoice %s', $invoice->invoice_number),
                    'status' => $invoice->status,
                    'amount' => (float) $invoice->grand_total,
                    'currency' => $invoice->currency,
                    'server_name' => $invoice->subscription?->server_name ?? $invoice->order?->server_name,
                    'existing_ticket_id' => $existing?->id,
                    'existing_ticket_number' => $existing?->ticket_number,
                ];
            })
            ->values()
            ->all();
    }

    public function refundEligibles(User $user): array
    {
        return BillingPayment::query()
            ->with(['invoice.order', 'invoice.subscription', 'refunds'])
            ->whereHas('invoice', fn ($query) => $query->where('user_id', $user->id))
            ->whereIn('status', [
                BillingPayment::STATUS_VERIFIED_PAID,
                BillingPayment::STATUS_REFUND_PENDING,
            ])
            ->latest('id')
            ->get()
            ->filter(function (BillingPayment $payment) {
                $refunded = round((float) $payment->refunds->where('status', 'completed')->sum('amount'), 2);

                return $refunded < (float) $payment->amount;
            })
            ->map(function (BillingPayment $payment) {
                $existing = $this->activeRefundTicketForPayment($payment);

                return [
                    'type' => Ticket::CATEGORY_REFUND,
                    'payment_id' => $payment->id,
                    'payment_number' => $payment->payment_number,
                    'invoice_id' => $payment->invoice_id,
                    'invoice_number' => $payment->invoice?->invoice_number,
                    'subject' => sprintf('Refund request for payment %s', $payment->payment_number),
                    'status' => $payment->status,
                    'amount' => (float) $payment->amount,
                    'currency' => $payment->currency,
                    'server_name' => $payment->invoice?->subscription?->server_name ?? $payment->invoice?->order?->server_name,
                    'existing_ticket_id' => $existing?->id,
                    'existing_ticket_number' => $existing?->ticket_number,
                ];
            })
            ->values()
            ->all();
    }

    public function supportServerEligibles(User $user): array
    {
        $servers = $user->accessibleServers()
            ->orderBy('servers.name')
            ->limit(24)
            ->get();

        return collect([[
            'type' => Ticket::CATEGORY_SUPPORT,
            'server_id' => 0,
            'server_label' => 'General / No Specific Server',
            'server_name' => 'General / No Specific Server',
            'server_uuid_short' => null,
            'subject' => 'General support request',
        ]])
            ->merge($servers->map(function (Server $server) {
                return [
                    'type' => Ticket::CATEGORY_SUPPORT,
                    'server_id' => $server->id,
                    'server_label' => sprintf('#%d • %s', $server->id, $server->name),
                    'server_name' => $server->name,
                    'server_uuid_short' => $server->uuidShort,
                    'subject' => sprintf('Support request for %s', $server->name),
                ];
            }))
            ->values()
            ->all();
    }

    public function activePaymentTicketForInvoice(BillingInvoice $invoice): ?Ticket
    {
        return Ticket::query()
            ->where('billing_invoice_id', $invoice->id)
            ->where('category', Ticket::CATEGORY_PAYMENT)
            ->whereIn('status', [Ticket::STATUS_WAITING_FOR_STAFF, Ticket::STATUS_WAITING_FOR_USER, Ticket::STATUS_RESOLVED])
            ->latest('id')
            ->first();
    }

    public function activeRefundTicketForPayment(BillingPayment $payment): ?Ticket
    {
        return Ticket::query()
            ->where('billing_payment_id', $payment->id)
            ->where('category', Ticket::CATEGORY_REFUND)
            ->whereIn('status', [Ticket::STATUS_WAITING_FOR_STAFF, Ticket::STATUS_WAITING_FOR_USER, Ticket::STATUS_RESOLVED])
            ->latest('id')
            ->first();
    }
}
