<?php

namespace Pterodactyl\Http\Controllers\Admin\Billing;

use Illuminate\View\View;
use Pterodactyl\Models\BillingRefund;
use Pterodactyl\Http\Controllers\Controller;

class RefundController extends Controller
{
    public function index(): View
    {
        return view('admin.billing.refunds', [
            'refunds' => BillingRefund::query()
                ->with(['payment.invoice.user', 'requestedBy'])
                ->latest()
                ->paginate(50),
        ]);
    }
}
