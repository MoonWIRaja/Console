<?php

namespace Pterodactyl\Http\Controllers\Admin\Billing;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Pterodactyl\Models\BillingInvoice;
use Pterodactyl\Http\Controllers\Controller;

class InvoiceController extends Controller
{
    private const STATUSES = [
        BillingInvoice::STATUS_DRAFT,
        BillingInvoice::STATUS_OPEN,
        BillingInvoice::STATUS_PROCESSING,
        BillingInvoice::STATUS_PAID,
        BillingInvoice::STATUS_EXPIRED,
        BillingInvoice::STATUS_VOID,
        BillingInvoice::STATUS_FAILED,
        BillingInvoice::STATUS_REFUNDED,
        BillingInvoice::STATUS_PARTIALLY_REFUNDED,
    ];

    public function index(Request $request): View
    {
        $status = $this->selectedStatus($request);
        $query = BillingInvoice::query()
            ->with(['user', 'order', 'subscription', 'payments'])
            ->latest();

        if ($status !== null) {
            $query->where('status', $status);
        }

        return view('admin.billing.invoices', [
            'invoices' => $query->paginate(50)->appends($request->except('page')),
            'invoiceStatusOptions' => $this->statusOptions(self::STATUSES),
            'selectedInvoiceStatus' => $status,
        ]);
    }

    public function view(BillingInvoice $billingInvoice): View
    {
        return view('admin.billing.invoice', [
            'invoice' => $billingInvoice->load([
                'user',
                'billingProfile',
                'order.user',
                'subscription.server',
                'items',
                'payments.refunds',
                'attempts',
            ]),
        ]);
    }

    private function selectedStatus(Request $request): ?string
    {
        $status = (string) $request->query('status', '');

        return in_array($status, self::STATUSES, true) ? $status : null;
    }

    private function statusOptions(array $statuses): array
    {
        return array_reduce($statuses, function (array $options, string $status): array {
            $options[$status] = ucwords(str_replace('_', ' ', $status));

            return $options;
        }, []);
    }
}
