<?php

namespace Pterodactyl\Services\Billing;

use Pterodactyl\Models\BillingInvoice;
use Pterodactyl\Models\BillingOrder;
use Pterodactyl\Models\BillingPayment;
use Pterodactyl\Models\BillingGatewayEvent;
use Pterodactyl\Models\BillingRefund;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class BillingSettlementReconciliationService
{
    private const TABLE_PER_PAGE = 10;

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

    public function report(array $filters = []): array
    {
        $openInvoiceStatuses = [
            BillingInvoice::STATUS_OPEN,
            BillingInvoice::STATUS_PROCESSING,
        ];
        $failedPaymentStatuses = [
            BillingPayment::STATUS_VERIFIED_FAILED,
            BillingPayment::STATUS_REFUND_FAILED,
        ];
        $refundStatuses = [
            BillingRefund::STATUS_REQUESTED,
            BillingRefund::STATUS_COMPLETED,
            BillingRefund::STATUS_FAILED,
        ];
        $webhookStatuses = [
            BillingGatewayEvent::STATUS_RECEIVED,
            BillingGatewayEvent::STATUS_PROCESSED,
            BillingGatewayEvent::STATUS_FAILED,
        ];
        $provisionStatuses = [
            BillingOrder::STATUS_PROVISION_FAILED,
            BillingOrder::STATUS_PROVISIONING,
            BillingOrder::STATUS_QUEUED_PROVISION,
            BillingOrder::STATUS_FAILED,
        ];

        return [
            'summary' => $this->summarize(),
            'open_invoices' => $this->paginateFilteredStatuses(
                BillingInvoice::query()->with(['user', 'order', 'subscription'])->latest(),
                $filters['open_invoices_status'] ?? null,
                $openInvoiceStatuses,
                $openInvoiceStatuses,
                'recon_invoices_page'
            ),
            'failed_payments' => $this->paginateFilteredStatuses(
                BillingPayment::query()->with(['invoice.user'])->latest(),
                $filters['failed_payments_status'] ?? null,
                $failedPaymentStatuses,
                $failedPaymentStatuses,
                'recon_payments_page'
            ),
            'pending_refunds' => $this->paginateFilteredStatuses(
                BillingRefund::query()->with(['payment.invoice.user', 'requestedBy'])->latest(),
                $filters['pending_refunds_status'] ?? null,
                $refundStatuses,
                [BillingRefund::STATUS_REQUESTED],
                'recon_refunds_page'
            ),
            'failed_webhook_events' => $this->paginateFilteredStatuses(
                BillingGatewayEvent::query()->latest(),
                $filters['failed_webhook_events_status'] ?? null,
                $webhookStatuses,
                [BillingGatewayEvent::STATUS_FAILED],
                'recon_webhooks_page'
            ),
            'provision_failures' => $this->paginateFilteredStatuses(
                BillingOrder::query()->with(['user', 'invoice'])->latest(),
                $filters['provision_failures_status'] ?? null,
                $provisionStatuses,
                [BillingOrder::STATUS_PROVISION_FAILED],
                'recon_provision_page'
            ),
        ];
    }

    private function paginateFilteredStatuses(
        Builder $query,
        ?string $selectedStatus,
        array $allowedStatuses,
        array $defaultStatuses,
        string $pageName
    ): LengthAwarePaginator {
        $status = is_string($selectedStatus) && in_array($selectedStatus, $allowedStatuses, true)
            ? $selectedStatus
            : null;

        $query->whereIn('status', $status ? [$status] : $defaultStatuses);

        return $query->paginate(self::TABLE_PER_PAGE, ['*'], $pageName);
    }
}
