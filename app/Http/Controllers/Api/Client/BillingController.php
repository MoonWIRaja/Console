<?php

namespace Pterodactyl\Http\Controllers\Api\Client;

use Illuminate\Http\Request;
use Pterodactyl\Models\BillingOrder;
use Pterodactyl\Models\BillingInvoice;
use Pterodactyl\Models\BillingSubscription;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Services\Billing\BillingCatalogService;
use Pterodactyl\Services\Billing\BillingProfileService;
use Pterodactyl\Services\Billing\BillingInvoiceService;
use Pterodactyl\Services\Billing\BillingPaymentService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Pterodactyl\Services\Billing\BillingSubscriptionService;
use Pterodactyl\Http\Requests\Api\Client\Billing\StoreBillingQuoteRequest;
use Pterodactyl\Http\Requests\Api\Client\Billing\StoreBillingOrderRequest;
use Pterodactyl\Http\Requests\Api\Client\Billing\StoreBillingProfileRequest;
use Pterodactyl\Http\Requests\Api\Client\Billing\ToggleBillingAutoRenewRequest;
use Pterodactyl\Http\Requests\Api\Client\Billing\UpgradeBillingSubscriptionRequest;

class BillingController extends ClientApiController
{
    public function __construct(
        private BillingCatalogService $catalogService,
        private BillingProfileService $profileService,
        private BillingInvoiceService $invoiceService,
        private BillingPaymentService $paymentService,
        private BillingSubscriptionService $subscriptionService,
    ) {
        parent::__construct();
    }

    public function catalog(): array
    {
        return [
            'data' => $this->catalogService->getClientCatalog(),
        ];
    }

    public function orders(Request $request): array
    {
        return [
            'data' => BillingOrder::query()
                ->with('invoice')
                ->where('user_id', $request->user()->id)
                ->latest()
                ->get()
                ->map(fn (BillingOrder $order) => $this->transformOrder($order))
                ->all(),
        ];
    }

    public function profile(Request $request): array
    {
        $profile = $this->profileService->getOrCreateForUser($request->user());

        return [
            'data' => $this->transformProfile($profile->toArray()),
        ];
    }

    public function updateProfile(StoreBillingProfileRequest $request): array
    {
        $profile = $this->profileService->update($request->user(), $request->validated());

        return [
            'data' => $this->transformProfile($profile->toArray()),
        ];
    }

    public function quote(StoreBillingQuoteRequest $request): array
    {
        return [
            'data' => $this->transformQuote($this->invoiceService->quoteNewOrder($request->user(), $request->validated())),
        ];
    }

    public function subscriptions(Request $request): array
    {
        return [
            'data' => BillingSubscription::query()
                ->with(['server', 'nodeConfig', 'gameProfile.egg.nest'])
                ->where('user_id', $request->user()->id)
                ->whereNotNull('server_id')
                ->whereIn('status', [
                    BillingSubscription::STATUS_ACTIVE,
                    BillingSubscription::STATUS_SUSPENDED,
                ])
                ->latest()
                ->get()
                ->map(fn (BillingSubscription $subscription) => $this->transformSubscription($subscription))
                ->all(),
        ];
    }

    public function store(StoreBillingOrderRequest $request): array
    {
        $order = $this->invoiceService->createNewOrderInvoice($request->user(), $request->validated());
        $payload = $this->transformOrder($order);
        $this->appendInvoicePaymentState($order->invoice, $payload, 'No payment was required for this order.');

        return ['data' => $payload];
    }

    public function invoices(Request $request): array
    {
        return [
            'data' => BillingInvoice::query()
                ->with(['items', 'payments.refunds', 'subscription', 'order'])
                ->where('user_id', $request->user()->id)
                ->latest()
                ->get()
                ->map(fn (BillingInvoice $invoice) => $this->transformInvoice($invoice))
                ->all(),
        ];
    }

    public function showInvoice(Request $request, BillingInvoice $billingInvoice): array
    {
        $invoice = $this->getInvoiceForRequest($request, $billingInvoice);

        return [
            'data' => $this->transformInvoice($invoice->loadMissing(['items', 'payments.refunds', 'subscription', 'order'])),
        ];
    }

    public function checkout(Request $request, BillingInvoice $billingInvoice): array
    {
        $invoice = $this->getInvoiceForRequest($request, $billingInvoice);
        $checkout = $this->paymentService->startCheckout($invoice);

        return [
            'data' => $this->transformCheckout($checkout),
        ];
    }

    public function retryPayment(Request $request, BillingInvoice $billingInvoice): array
    {
        $invoice = $this->getInvoiceForRequest($request, $billingInvoice);
        $checkout = $this->paymentService->retryCheckout($invoice);

        return [
            'data' => $this->transformCheckout($checkout),
        ];
    }

    public function renew(Request $request, BillingSubscription $billingSubscription): array
    {
        $subscription = $this->getSubscriptionForRequest($request, $billingSubscription);
        $invoice = $this->invoiceService->createRenewalInvoice($subscription);
        $payload = $this->transformSubscription($subscription->fresh());
        $payload['invoice'] = $this->transformInvoice($invoice);
        $this->appendInvoicePaymentState($invoice, $payload, 'No payment was required for this renewal.');

        return [
            'data' => $payload,
        ];
    }

    public function upgradeQuote(UpgradeBillingSubscriptionRequest $request, BillingSubscription $billingSubscription): array
    {
        $subscription = $this->getSubscriptionForRequest($request, $billingSubscription);

        return [
            'data' => $this->transformQuote($this->invoiceService->quoteUpgrade($subscription, $request->validated())),
        ];
    }

    public function upgrade(UpgradeBillingSubscriptionRequest $request, BillingSubscription $billingSubscription): array
    {
        $subscription = $this->getSubscriptionForRequest($request, $billingSubscription);
        $invoice = $this->invoiceService->createUpgradeInvoice($subscription, $request->validated());
        $payload = $this->transformSubscription($subscription->fresh());
        $payload['invoice'] = $this->transformInvoice($invoice);
        $this->appendInvoicePaymentState($invoice, $payload, 'No payment was required for this upgrade.');

        return [
            'data' => $payload,
        ];
    }

    public function toggleAutoRenew(ToggleBillingAutoRenewRequest $request, BillingSubscription $billingSubscription): array
    {
        $subscription = $this->getSubscriptionForRequest($request, $billingSubscription);
        $subscription = $this->subscriptionService->toggleAutoRenew($subscription, (bool) $request->validated()['auto_renew']);

        return [
            'data' => $this->transformSubscription($subscription),
        ];
    }

    private function transformOrder(BillingOrder $order): array
    {
        $order->loadMissing('invoice');

        return [
            'id' => $order->id,
            'status' => $order->status,
            'order_type' => $order->order_type,
            'server_name' => $order->server_name,
            'node_name' => $order->node_name,
            'game_name' => $order->game_name,
            'cpu_cores' => $order->cpu_cores,
            'memory_gb' => $order->memory_gb,
            'disk_gb' => $order->disk_gb,
            'total' => (float) $order->total,
            'server_id' => $order->server_id,
            'billing_invoice_id' => $order->billing_invoice_id,
            'admin_notes' => $order->admin_notes,
            'payment_verified_at' => optional($order->payment_verified_at)->toIso8601String(),
            'provision_attempted_at' => optional($order->provision_attempted_at)->toIso8601String(),
            'provision_failure_code' => $order->provision_failure_code,
            'provision_failure_message' => $order->provision_failure_message,
            'created_at' => optional($order->created_at)->toIso8601String(),
            'approved_at' => optional($order->approved_at)->toIso8601String(),
            'provisioned_at' => optional($order->provisioned_at)->toIso8601String(),
            'invoice' => $order->invoice ? $this->transformInvoice($order->invoice) : null,
        ];
    }

    private function appendInvoicePaymentState(BillingInvoice $invoice, array &$payload, string $zeroAmountReason): void
    {
        if ((float) $invoice->grand_total <= 0) {
            $this->paymentService->settleZeroAmountInvoice($invoice, $zeroAmountReason);
            $payload['invoice'] = $this->transformInvoice($invoice->fresh(['items', 'payments.refunds', 'subscription', 'order']));
            $payload['auto_settled'] = true;

            return;
        }

        try {
            $payload['checkout'] = $this->transformCheckout($this->paymentService->startCheckout($invoice));
        } catch (DisplayException $exception) {
            $payload['checkout_error'] = $exception->getMessage();
        }
    }

    private function transformSubscription(BillingSubscription $subscription): array
    {
        $subscription->loadMissing('server', 'nodeConfig', 'gameProfile.egg.nest');

        $upgradeLimits = $subscription->nodeConfig
            ? $this->catalogService->getSubscriptionUpgradeLimits($subscription)
            : [
                'max_cpu' => $subscription->cpu_cores,
                'max_memory_gb' => $subscription->memory_gb,
                'max_disk_gb' => $subscription->disk_gb,
                'disk_step_gb' => BillingCatalogService::DISK_STEP_GB,
                'free_allocations' => 0,
            ];

        $pricing = $subscription->nodeConfig
            ? [
                'per_vcore' => (float) $subscription->nodeConfig->price_per_vcore,
                'per_gb_ram' => (float) $subscription->nodeConfig->price_per_gb_ram,
                'per_10gb_disk' => (float) $subscription->nodeConfig->price_per_10gb_disk,
            ]
            : [
                'per_vcore' => (float) $subscription->price_per_vcore,
                'per_gb_ram' => (float) $subscription->price_per_gb_ram,
                'per_10gb_disk' => (float) $subscription->price_per_10gb_disk,
            ];

        return [
            'id' => $subscription->id,
            'status' => $subscription->status,
            'server_id' => $subscription->server_id,
            'server_identifier' => $subscription->server?->uuidShort,
            'server_name' => $subscription->server_name,
            'node_name' => $subscription->node_name,
            'game_name' => $subscription->game_name,
            'nest_name' => $subscription->gameProfile?->egg?->nest?->name,
            'cpu_cores' => $subscription->cpu_cores,
            'memory_gb' => $subscription->memory_gb,
            'disk_gb' => $subscription->disk_gb,
            'renewal_period_months' => $subscription->renewal_period_months,
            'recurring_total' => (float) $subscription->recurring_total,
            'auto_renew' => $subscription->auto_renew,
            'gateway_provider' => $subscription->gateway_provider,
            'auto_renew_available' => !blank($subscription->gateway_token_reference),
            'auto_renew_unavailable_reason' => blank($subscription->gateway_token_reference)
                ? 'Auto-renew requires a tokenized card payment. QR and online banking payments usually do not create a reusable token.'
                : null,
            'renews_at' => optional($subscription->renews_at)->toIso8601String(),
            'next_invoice_at' => optional($subscription->next_invoice_at)->toIso8601String(),
            'grace_suspend_at' => optional($subscription->grace_suspend_at)->toIso8601String(),
            'grace_delete_at' => optional($subscription->grace_delete_at)->toIso8601String(),
            'failed_payment_count' => $subscription->failed_payment_count,
            'renewal_reminder_sent_at' => optional($subscription->renewal_reminder_sent_at)->toIso8601String(),
            'renewed_at' => optional($subscription->renewed_at)->toIso8601String(),
            'upgraded_at' => optional($subscription->upgraded_at)->toIso8601String(),
            'suspended_at' => optional($subscription->suspended_at)->toIso8601String(),
            'renew_available_at' => optional($subscription->renewAvailableAt())->toIso8601String(),
            'deletion_scheduled_at' => optional($subscription->deletion_scheduled_at)->toIso8601String(),
            'deleted_at' => optional($subscription->deleted_at)->toIso8601String(),
            'can_renew' => $subscription->isRenewWindowOpen(),
            'can_upgrade' => $subscription->status === BillingSubscription::STATUS_ACTIVE && $subscription->hasAttachedServer(),
            'pricing' => $pricing,
            'limits' => $upgradeLimits,
        ];
    }

    private function transformInvoice(BillingInvoice $invoice): array
    {
        $invoice->loadMissing(['items', 'payments.refunds', 'subscription', 'order']);

        return [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'status' => $invoice->status,
            'type' => $invoice->type,
            'currency' => $invoice->currency,
            'subtotal' => (float) $invoice->subtotal,
            'tax_total' => (float) $invoice->tax_total,
            'grand_total' => (float) $invoice->grand_total,
            'billing_order_id' => $invoice->billing_order_id,
            'subscription_id' => $invoice->subscription_id,
            'issued_at' => optional($invoice->issued_at)->toIso8601String(),
            'due_at' => optional($invoice->due_at)->toIso8601String(),
            'paid_at' => optional($invoice->paid_at)->toIso8601String(),
            'voided_at' => optional($invoice->voided_at)->toIso8601String(),
            'notes' => $invoice->notes,
            'items' => $invoice->items->map(fn ($item) => [
                'id' => $item->id,
                'type' => $item->type,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_amount' => (float) $item->unit_amount,
                'line_subtotal' => (float) $item->line_subtotal,
                'meta' => $item->meta,
            ])->all(),
            'payments' => $invoice->payments->map(fn ($payment) => [
                'id' => $payment->id,
                'payment_number' => $payment->payment_number,
                'provider' => $payment->provider,
                'provider_transaction_id' => $payment->provider_transaction_id,
                'provider_payment_method' => $payment->provider_payment_method,
                'provider_status' => $payment->provider_status,
                'amount' => (float) $payment->amount,
                'currency' => $payment->currency,
                'status' => $payment->status,
                'paid_at' => optional($payment->paid_at)->toIso8601String(),
                'refunds' => $payment->refunds->map(fn ($refund) => [
                    'id' => $refund->id,
                    'refund_number' => $refund->refund_number,
                    'amount' => (float) $refund->amount,
                    'status' => $refund->status,
                    'requested_at' => optional($refund->requested_at)->toIso8601String(),
                    'completed_at' => optional($refund->completed_at)->toIso8601String(),
                ])->all(),
            ])->all(),
        ];
    }

    private function transformProfile(array $profile): array
    {
        return [
            'legal_name' => $profile['legal_name'] ?? null,
            'company_name' => $profile['company_name'] ?? null,
            'email' => $profile['email'] ?? null,
            'phone' => $profile['phone'] ?? null,
            'address_line_1' => $profile['address_line_1'] ?? null,
            'address_line_2' => $profile['address_line_2'] ?? null,
            'city' => $profile['city'] ?? null,
            'state' => $profile['state'] ?? null,
            'postcode' => $profile['postcode'] ?? null,
            'country_code' => $profile['country_code'] ?? null,
            'tax_id' => $profile['tax_id'] ?? null,
            'is_business' => (bool) ($profile['is_business'] ?? false),
        ];
    }

    private function transformQuote(array $quote): array
    {
        return $quote;
    }

    private function transformCheckout(array $checkout): array
    {
        /** @var \Pterodactyl\Models\BillingPaymentAttempt $attempt */
        $attempt = $checkout['attempt'];

        return [
            'attempt_id' => $attempt->id,
            'checkout_reference' => $attempt->checkout_reference,
            'provider' => $checkout['checkout']['provider'],
            'method' => $checkout['checkout']['method'],
            'url' => $checkout['checkout']['url'],
            'payload' => $checkout['checkout']['payload'],
        ];
    }

    private function getSubscriptionForRequest(Request $request, BillingSubscription $billingSubscription): BillingSubscription
    {
        if ($request->user()->id !== $billingSubscription->user_id && !$request->user()->root_admin) {
            throw new NotFoundHttpException(trans('exceptions.api.resource_not_found'));
        }

        return $billingSubscription;
    }

    private function getInvoiceForRequest(Request $request, BillingInvoice $billingInvoice): BillingInvoice
    {
        if ($request->user()->id !== $billingInvoice->user_id && !$request->user()->root_admin) {
            throw new NotFoundHttpException(trans('exceptions.api.resource_not_found'));
        }

        return $billingInvoice;
    }
}
