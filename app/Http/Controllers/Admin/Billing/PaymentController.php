<?php

namespace Pterodactyl\Http\Controllers\Admin\Billing;

use Throwable;
use Illuminate\Support\Arr;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Models\BillingPayment;
use Pterodactyl\Models\BillingRefund;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Billing\BillingPaymentService;
use Pterodactyl\Http\Requests\Admin\Billing\BillingRefundRequest;

class PaymentController extends Controller
{
    public function __construct(
        private AlertsMessageBag $alert,
        private BillingPaymentService $paymentService,
    ) {
    }

    public function index(): View
    {
        return view('admin.billing.payments', [
            'payments' => BillingPayment::query()
                ->with(['invoice.user', 'refunds'])
                ->latest()
                ->paginate(50),
        ]);
    }

    public function view(BillingPayment $billingPayment): View
    {
        return view('admin.billing.payment', [
            'payment' => $billingPayment->load(['invoice.user', 'invoice.order', 'invoice.subscription', 'refunds.requestedBy']),
        ]);
    }

    public function refund(BillingRefundRequest $request, BillingPayment $billingPayment): RedirectResponse
    {
        try {
            $refund = $this->paymentService->refundPayment(
                $billingPayment,
                (float) $request->input('amount'),
                $request->input('reason'),
                $request->user()
            );
        } catch (Throwable $exception) {
            report($exception);
            $this->alert->danger($exception->getMessage())->flash();

            return redirect()->route('admin.billing.payments.view', $billingPayment->id);
        }

        if ($refund->status !== BillingRefund::STATUS_COMPLETED) {
            $reason = Arr::get($refund->raw_response, 'error_desc')
                ?? Arr::get($refund->raw_response, 'Description')
                ?? Arr::get($refund->raw_response, 'StatName')
                ?? Arr::get($refund->raw_response, 'message')
                ?? 'The refund request was rejected by the payment provider.';

            $this->alert->danger($reason)->flash();

            return redirect()->route('admin.billing.payments.view', $billingPayment->id);
        }

        $this->alert->success('Refund request submitted successfully.')->flash();

        return redirect()->route('admin.billing.payments.view', $billingPayment->id);
    }
}
