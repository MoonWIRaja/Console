<?php

namespace Pterodactyl\Http\Controllers\Admin\Billing;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Pterodactyl\Models\BillingRefund;
use Pterodactyl\Http\Controllers\Controller;

class RefundController extends Controller
{
    private const STATUSES = [
        BillingRefund::STATUS_REQUESTED,
        BillingRefund::STATUS_COMPLETED,
        BillingRefund::STATUS_FAILED,
    ];

    public function index(Request $request): View
    {
        $status = $this->selectedStatus($request);
        $query = BillingRefund::query()
            ->with(['payment.invoice.user', 'requestedBy'])
            ->latest();

        if ($status !== null) {
            $query->where('status', $status);
        }

        return view('admin.billing.refunds', [
            'refunds' => $query->paginate(50)->appends($request->except('page')),
            'refundStatusOptions' => $this->statusOptions(self::STATUSES),
            'selectedRefundStatus' => $status,
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
