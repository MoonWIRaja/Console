<?php

namespace Pterodactyl\Http\Controllers\Admin\Billing;

use Illuminate\View\View;
use Pterodactyl\Models\BillingInvoice;
use Pterodactyl\Http\Controllers\Controller;

class InvoiceController extends Controller
{
    public function index(): View
    {
        return view('admin.billing.invoices', [
            'invoices' => BillingInvoice::query()
                ->with(['user', 'order', 'subscription', 'payments'])
                ->latest()
                ->paginate(50),
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
}
