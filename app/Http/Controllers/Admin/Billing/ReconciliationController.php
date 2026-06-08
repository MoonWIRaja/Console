<?php

namespace Pterodactyl\Http\Controllers\Admin\Billing;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Pterodactyl\Models\BillingOrder;
use Pterodactyl\Models\BillingInvoice;
use Pterodactyl\Models\BillingPayment;
use Pterodactyl\Models\BillingGatewayEvent;
use Pterodactyl\Models\BillingRefund;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Billing\BillingSettlementReconciliationService;

class ReconciliationController extends Controller
{
    public function __construct(private BillingSettlementReconciliationService $reconciliationService)
    {
    }

    public function index(Request $request): View
    {
        $filters = [
            'open_invoices_status' => $this->selectedStatus($request, 'recon_invoices_status', [
                BillingInvoice::STATUS_OPEN,
                BillingInvoice::STATUS_PROCESSING,
            ]),
            'failed_payments_status' => $this->selectedStatus($request, 'recon_payments_status', [
                BillingPayment::STATUS_VERIFIED_FAILED,
                BillingPayment::STATUS_REFUND_FAILED,
            ]),
            'pending_refunds_status' => $this->selectedStatus($request, 'recon_refunds_status', [
                BillingRefund::STATUS_REQUESTED,
                BillingRefund::STATUS_COMPLETED,
                BillingRefund::STATUS_FAILED,
            ]),
            'failed_webhook_events_status' => $this->selectedStatus($request, 'recon_webhooks_status', [
                BillingGatewayEvent::STATUS_RECEIVED,
                BillingGatewayEvent::STATUS_PROCESSED,
                BillingGatewayEvent::STATUS_FAILED,
            ]),
            'provision_failures_status' => $this->selectedStatus($request, 'recon_provision_status', [
                BillingOrder::STATUS_PROVISION_FAILED,
                BillingOrder::STATUS_PROVISIONING,
                BillingOrder::STATUS_QUEUED_PROVISION,
                BillingOrder::STATUS_FAILED,
            ]),
        ];

        $report = $this->reconciliationService->report($filters);

        foreach ([
            'open_invoices' => 'recon_invoices_page',
            'failed_payments' => 'recon_payments_page',
            'pending_refunds' => 'recon_refunds_page',
            'failed_webhook_events' => 'recon_webhooks_page',
            'provision_failures' => 'recon_provision_page',
        ] as $key => $pageName) {
            if (($report[$key] ?? null) instanceof LengthAwarePaginator) {
                $report[$key]->appends($request->except($pageName));
            }
        }

        return view('admin.billing.reconciliation', $report + [
            'openInvoiceStatusOptions' => $this->statusOptions([
                BillingInvoice::STATUS_OPEN,
                BillingInvoice::STATUS_PROCESSING,
            ]),
            'failedPaymentStatusOptions' => $this->statusOptions([
                BillingPayment::STATUS_VERIFIED_FAILED,
                BillingPayment::STATUS_REFUND_FAILED,
            ]),
            'refundStatusOptions' => $this->statusOptions([
                BillingRefund::STATUS_REQUESTED,
                BillingRefund::STATUS_COMPLETED,
                BillingRefund::STATUS_FAILED,
            ]),
            'webhookStatusOptions' => $this->statusOptions([
                BillingGatewayEvent::STATUS_RECEIVED,
                BillingGatewayEvent::STATUS_PROCESSED,
                BillingGatewayEvent::STATUS_FAILED,
            ]),
            'provisionStatusOptions' => $this->statusOptions([
                BillingOrder::STATUS_PROVISION_FAILED,
                BillingOrder::STATUS_PROVISIONING,
                BillingOrder::STATUS_QUEUED_PROVISION,
                BillingOrder::STATUS_FAILED,
            ]),
            'selectedOpenInvoiceStatus' => $filters['open_invoices_status'],
            'selectedFailedPaymentStatus' => $filters['failed_payments_status'],
            'selectedRefundStatus' => $filters['pending_refunds_status'],
            'selectedWebhookStatus' => $filters['failed_webhook_events_status'],
            'selectedProvisionStatus' => $filters['provision_failures_status'],
        ]);
    }

    private function selectedStatus(Request $request, string $key, array $allowedStatuses): ?string
    {
        $status = (string) $request->query($key, '');

        return in_array($status, $allowedStatuses, true) ? $status : null;
    }

    private function statusOptions(array $statuses): array
    {
        return array_reduce($statuses, function (array $options, string $status): array {
            $options[$status] = ucwords(str_replace('_', ' ', $status));

            return $options;
        }, []);
    }
}
