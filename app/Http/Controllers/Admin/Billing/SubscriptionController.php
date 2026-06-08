<?php

namespace Pterodactyl\Http\Controllers\Admin\Billing;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Pterodactyl\Models\BillingSubscription;
use Pterodactyl\Http\Controllers\Controller;

class SubscriptionController extends Controller
{
    private const STATUSES = [
        BillingSubscription::STATUS_PENDING_ACTIVATION,
        BillingSubscription::STATUS_ACTIVE,
        BillingSubscription::STATUS_PAST_DUE,
        BillingSubscription::STATUS_SUSPENDED,
        BillingSubscription::STATUS_CANCELLED,
        BillingSubscription::STATUS_DELETED,
    ];

    public function index(Request $request): View
    {
        $status = $this->selectedStatus($request);
        $query = BillingSubscription::query()
            ->with(['user', 'server', 'lastPaidInvoice'])
            ->latest();

        if ($status !== null) {
            $query->where('status', $status);
        }

        return view('admin.billing.subscriptions', [
            'subscriptions' => $query->paginate(50)->appends($request->except('page')),
            'subscriptionStatusOptions' => $this->statusOptions(self::STATUSES),
            'selectedSubscriptionStatus' => $status,
        ]);
    }

    private function selectedStatus(Request $request): ?string
    {
        if (!$request->query->has('status')) {
            return BillingSubscription::STATUS_ACTIVE;
        }

        $status = (string) $request->query('status', '');

        if ($status === '') {
            return null;
        }

        return in_array($status, self::STATUSES, true) ? $status : BillingSubscription::STATUS_ACTIVE;
    }

    private function statusOptions(array $statuses): array
    {
        return array_reduce($statuses, function (array $options, string $status): array {
            $options[$status] = ucwords(str_replace('_', ' ', $status));

            return $options;
        }, []);
    }
}
