<?php

namespace Pterodactyl\Services\Billing;

use Pterodactyl\Models\BillingInvoice;
use Pterodactyl\Models\BillingOrder;
use Pterodactyl\Models\BillingPayment;
use Pterodactyl\Models\BillingGatewayEvent;
use Pterodactyl\Models\BillingRefund;

class BillingSettlementReconciliationService
{
    public function summarize(): array
    {
        $verifiedGross = (float) BillingPayment::query()
            ->where('status', BillingPayment::STATUS_VERIFIED_PAID)
            ->sum('amount');
        $refundedTotal = (float) BillingRefund::query()
            ->where('status', BillingRefund::STATUS_COMPLETED)
            ->sum('amount');
        $outstandingInvoices = (float) BillingInvoice::query()
            ->whereIn('status', [
                BillingInvoice::STATUS_OPEN,
                BillingInvoice::STATUS_PROCESSING,
            ])
            ->sum('grand_total');

        return [
            'open_invoices' => BillingInvoice::query()->whereIn('status', [
                BillingInvoice::STATUS_OPEN,
                BillingInvoice::STATUS_PROCESSING,
            ])->count(),
            'paid_invoices' => BillingInvoice::query()->where('status', BillingInvoice::STATUS_PAID)->count(),
            'failed_payments' => BillingPayment::query()->whereIn('status', [
                BillingPayment::STATUS_VERIFIED_FAILED,
                BillingPayment::STATUS_REFUND_FAILED,
            ])->count(),
            'refunds_pending' => BillingRefund::query()->where('status', BillingRefund::STATUS_REQUESTED)->count(),
            'verified_gross' => round($verifiedGross, 2),
            'refunded_total' => round($refundedTotal, 2),
            'net_collected' => round($verifiedGross - $refundedTotal, 2),
            'outstanding_invoices' => round($outstandingInvoices, 2),
            'provision_failures' => BillingOrder::query()->where('status', BillingOrder::STATUS_PROVISION_FAILED)->count(),
            'webhook_failures' => BillingGatewayEvent::query()->where('status', BillingGatewayEvent::STATUS_FAILED)->count(),
        ];
    }

    public function report(): array
    {
        return [
            'summary' => $this->summarize(),
            'open_invoices' => BillingInvoice::query()
                ->with(['user', 'order', 'subscription'])
                ->whereIn('status', [
                    BillingInvoice::STATUS_OPEN,
                    BillingInvoice::STATUS_PROCESSING,
                ])
                ->latest()
                ->limit(20)
                ->get(),
            'failed_payments' => BillingPayment::query()
                ->with(['invoice.user'])
                ->whereIn('status', [
                    BillingPayment::STATUS_VERIFIED_FAILED,
                    BillingPayment::STATUS_REFUND_FAILED,
                ])
                ->latest()
                ->limit(20)
                ->get(),
            'pending_refunds' => BillingRefund::query()
                ->with(['payment.invoice.user', 'requestedBy'])
                ->where('status', BillingRefund::STATUS_REQUESTED)
                ->latest()
                ->limit(20)
                ->get(),
            'failed_webhook_events' => BillingGatewayEvent::query()
                ->where('status', BillingGatewayEvent::STATUS_FAILED)
                ->latest()
                ->limit(20)
                ->get(),
            'provision_failures' => BillingOrder::query()
                ->with(['user', 'invoice'])
                ->where('status', BillingOrder::STATUS_PROVISION_FAILED)
                ->latest()
                ->limit(20)
                ->get(),
        ];
    }
}
