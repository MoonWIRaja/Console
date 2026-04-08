<?php

namespace Pterodactyl\Http\Controllers\Admin\Billing;

use Illuminate\View\View;
use Pterodactyl\Models\BillingSubscription;
use Pterodactyl\Http\Controllers\Controller;

class SubscriptionController extends Controller
{
    public function index(): View
    {
        return view('admin.billing.subscriptions', [
            'subscriptions' => BillingSubscription::query()
                ->with(['user', 'server', 'lastPaidInvoice'])
                ->latest()
                ->paginate(50),
        ]);
    }
}
