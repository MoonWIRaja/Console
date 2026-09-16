<?php

namespace Pterodactyl\Http\Controllers\Admin\Billing;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Models\User;
use Pterodactyl\Models\BillingCoupon;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Billing\BillingCouponService;
use Pterodactyl\Http\Requests\Admin\Billing\BillingCouponRequest;
use Pterodactyl\Http\Requests\Admin\Billing\BillingCouponAssignRequest;

class CouponController extends Controller
{
    private const STATUS_FILTERS = [
        'active' => 'Active',
        'inactive' => 'Inactive',
    ];

    public function __construct(
        private AlertsMessageBag $alert,
        private BillingCouponService $couponService,
    ) {
    }

    public function index(Request $request): View
    {
        $status = $this->selectedStatus($request);
        $query = BillingCoupon::query()->with('assignedUser')->orderByDesc('id');

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        return view('admin.billing.coupons', [
            'coupons' => $query->paginate(50)->appends($request->except('page')),
            'couponStatusOptions' => self::STATUS_FILTERS,
            'selectedCouponStatus' => $status,
            'expiryMonthOptions' => BillingCouponRequest::EXPIRY_MONTH_OPTIONS,
            'assignableUsers' => User::query()->orderBy('username')->get(['id', 'username', 'email']),
        ]);
    }

    public function store(BillingCouponRequest $request): RedirectResponse
    {
        $data = $request->normalize();
        if (empty($data['code'])) {
            $data['code'] = BillingCoupon::generateCode();
        }

        BillingCoupon::query()->create($data);
        $this->alert->success('Coupon created successfully.')->flash();

        return redirect()->route('admin.billing.coupons');
    }

    public function update(BillingCouponRequest $request, BillingCoupon $billingCoupon): RedirectResponse
    {
        $data = $request->normalize();
        if (empty($data['code'])) {
            unset($data['code']);
        }

        $billingCoupon->fill($data)->saveOrFail();
        $this->alert->success('Coupon updated successfully.')->flash();

        return redirect()->route('admin.billing.coupons');
    }

    public function assign(BillingCouponAssignRequest $request): RedirectResponse
    {
        $pendingEmails = $request->pendingEmails();
        $coupons = $this->couponService->createForUsers(
            $request->normalizeShared(),
            $request->userIds(),
            $pendingEmails
        );

        $this->alert->success(sprintf(
            'Created and emailed %d personal coupon%s.',
            count($coupons),
            count($coupons) === 1 ? '' : 's'
        ))->flash();

        if (!empty($pendingEmails)) {
            $this->alert->info(sprintf(
                '%d of those %s for an email with no account yet — it will activate automatically once that person registers with the same address: %s',
                count($pendingEmails),
                count($pendingEmails) === 1 ? 'is' : 'are',
                implode(', ', array_slice($pendingEmails, 0, 10)) . (count($pendingEmails) > 10 ? ', …' : '')
            ))->flash();
        }

        return redirect()->route('admin.billing.coupons');
    }

    public function csvTemplate(): Response
    {
        return response("email\n", 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="coupon-users-template.csv"',
        ]);
    }

    public function toggle(BillingCoupon $billingCoupon): RedirectResponse
    {
        $billingCoupon->is_active = !$billingCoupon->is_active;
        $billingCoupon->saveOrFail();

        $this->alert->success(sprintf(
            'Coupon "%s" is now %s.',
            $billingCoupon->code,
            $billingCoupon->is_active ? 'active' : 'inactive'
        ))->flash();

        return redirect()->route('admin.billing.coupons');
    }

    public function destroy(BillingCoupon $billingCoupon): RedirectResponse
    {
        $billingCoupon->delete();
        $this->alert->success('Coupon deleted successfully.')->flash();

        return redirect()->route('admin.billing.coupons');
    }

    private function selectedStatus(Request $request): ?string
    {
        $status = (string) $request->query('status', '');

        return array_key_exists($status, self::STATUS_FILTERS) ? $status : null;
    }
}
