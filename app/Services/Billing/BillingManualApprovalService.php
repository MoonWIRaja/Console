<?php

namespace Pterodactyl\Services\Billing;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Pterodactyl\Models\User;
use Pterodactyl\Models\BillingOrder;
use Pterodactyl\Models\BillingInvoice;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Notifications\BillingManualOrderRejected;
use Pterodactyl\Services\Tickets\BillingTicketAutomationService;

class BillingManualApprovalService
{
    public function __construct(
        private BillingPaymentService $paymentService,
        private BillingOrderProvisionService $provisionService,
        private BillingTicketAutomationService $ticketAutomation,
    ) {
    }

    public function approve(BillingOrder $order, User $admin, ?string $notes = null): BillingOrder
    {
        $order->loadMissing(['invoice.user', 'invoice.subscription', 'server']);
        $invoice = $order->invoice;

        if (!$invoice instanceof BillingInvoice) {
            throw new DisplayException('This billing order does not have an invoice attached.');
        }

        if (!in_array($order->status, [
            BillingOrder::STATUS_AWAITING_PAYMENT,
            BillingOrder::STATUS_PENDING,
        ], true)) {
            throw new DisplayException('Only unpaid billing orders can be approved.');
        }

        if ($order->status === BillingOrder::STATUS_REJECTED) {
            throw new DisplayException('Rejected billing orders cannot be approved.');
        }

        if ($invoice->status === BillingInvoice::STATUS_VOID) {
            throw new DisplayException('This invoice has already been voided and cannot be approved.');
        }

        $sendReceiptImmediately = $invoice->type !== BillingInvoice::TYPE_NEW_SERVER;
        $payment = $this->paymentService->recordManualApproval($invoice, $admin, $notes, $sendReceiptImmediately);

        $order = $order->fresh(['invoice', 'server', 'user']);

        if ($invoice->type === BillingInvoice::TYPE_NEW_SERVER) {
            $order = $this->provisionService->handle($order, $admin);
            $this->paymentService->notifyPaymentReceipt($payment->fresh('invoice.user', 'invoice.order', 'invoice.subscription'));
        }

        return $order->fresh(['invoice', 'server', 'user']);
    }

    public function reject(BillingOrder $order, User $admin, ?string $notes = null): BillingOrder
    {
        $order->loadMissing(['invoice.user']);

        if (!in_array($order->status, [
            BillingOrder::STATUS_AWAITING_PAYMENT,
            BillingOrder::STATUS_PENDING,
        ], true)) {
            throw new DisplayException('Only unpaid billing orders can be rejected.');
        }

        return DB::transaction(function () use ($order, $admin, $notes) {
            $order->forceFill([
                'status' => BillingOrder::STATUS_REJECTED,
                'approved_by' => $admin->id,
                'admin_notes' => $notes,
                'rejected_at' => CarbonImmutable::now(),
            ])->saveOrFail();

            if ($order->invoice && $order->invoice->status !== BillingInvoice::STATUS_PAID) {
                $order->invoice->forceFill([
                    'status' => BillingInvoice::STATUS_VOID,
                    'voided_at' => CarbonImmutable::now(),
                    'provider_status' => 'rejected_by_admin',
                    'notes' => trim(implode("\n\n", array_filter([
                        $order->invoice->notes,
                        $notes,
                    ]))),
                ])->saveOrFail();
            }

            $order->user?->notify(new BillingManualOrderRejected($order->fresh(['invoice', 'user'])));
            $this->ticketAutomation->markOrderRejected($order->fresh(['invoice']));

            return $order->fresh(['invoice', 'user']);
        });
    }
}
