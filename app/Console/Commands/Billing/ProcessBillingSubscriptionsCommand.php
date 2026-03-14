<?php

namespace Pterodactyl\Console\Commands\Billing;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\CarbonImmutable;
use Pterodactyl\Models\BillingOrder;
use Pterodactyl\Models\BillingInvoice;
use Pterodactyl\Models\BillingRefund;
use Pterodactyl\Models\BillingPayment;
use Pterodactyl\Models\BillingSubscription;
use Pterodactyl\Notifications\BillingPaymentActionRequired;
use Pterodactyl\Notifications\BillingRenewalReminder;
use Pterodactyl\Notifications\BillingSubscriptionSuspended;
use Pterodactyl\Notifications\BillingSubscriptionDeletionScheduled;
use Pterodactyl\Services\Billing\BillingInvoiceService;
use Pterodactyl\Services\Billing\BillingPaymentService;
use Pterodactyl\Services\Billing\StripeCheckoutService;
use Pterodactyl\Services\Billing\BillingSubscriptionService;
use Pterodactyl\Services\Billing\BillingWebhookReplayService;
use Pterodactyl\Services\Servers\SuspensionService;
use Pterodactyl\Services\Servers\ServerDeletionService;

class ProcessBillingSubscriptionsCommand extends Command
{
    protected $signature = 'billing:process-subscriptions';

    protected $description = 'Send renewal reminders and process overdue billing subscription suspensions and deletions.';

    public function __construct(
        private BillingInvoiceService $invoiceService,
        private BillingPaymentService $paymentService,
        private BillingSubscriptionService $subscriptionService,
        private BillingWebhookReplayService $webhookReplayService,
        private SuspensionService $suspensionService,
        private ServerDeletionService $serverDeletionService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $now = CarbonImmutable::now();

        $this->replayPendingGatewayEvents();
        $this->reconcileRefundPaymentStates();
        $this->reconcileRefundedSubscriptionEffects($now);
        $this->reconcileCompletedOrders($now);
        $this->expireOverdueInvoices($now);
        $this->deleteOrphanedSubscriptions($now);
        $this->issueRenewalInvoices($now);
        $this->sendRenewalReminders($now);
        $this->attemptAutomaticRenewals($now);
        $this->suspendRefundCancelledSubscriptions($now);
        $this->deleteRefundCancelledSubscriptions($now);
        $this->markPastDueSubscriptions($now);
        $this->suspendOverdueSubscriptions($now);
        $this->deleteExpiredSubscriptions($now);

        return self::SUCCESS;
    }

    private function replayPendingGatewayEvents(): void
    {
        try {
            $this->webhookReplayService->replayPending();
        } catch (\Throwable $exception) {
            Log::warning('Failed to replay pending billing gateway events.', [
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function reconcileRefundedSubscriptionEffects(CarbonImmutable $now): void
    {
        BillingInvoice::query()
            ->with(['subscription.server', 'order'])
            ->where('status', BillingInvoice::STATUS_REFUNDED)
            ->whereIn('type', [
                BillingInvoice::TYPE_NEW_SERVER,
                BillingInvoice::TYPE_RENEWAL,
                BillingInvoice::TYPE_UPGRADE,
            ])
            ->chunkById(100, function ($invoices) use ($now) {
                foreach ($invoices as $invoice) {
                    $subscription = $invoice->subscription ?: $this->resolveSubscriptionForRefundedInvoice($invoice);
                    if (!$subscription) {
                        continue;
                    }

                    try {
                        if (in_array($invoice->type, [
                            BillingInvoice::TYPE_NEW_SERVER,
                            BillingInvoice::TYPE_RENEWAL,
                        ], true) && $subscription->status !== BillingSubscription::STATUS_DELETED) {
                            $this->subscriptionService->scheduleTerminationAfterRefund($subscription, $now);
                        }

                        if ($invoice->type === BillingInvoice::TYPE_UPGRADE && !in_array($subscription->status, [
                            BillingSubscription::STATUS_CANCELLED,
                            BillingSubscription::STATUS_DELETED,
                        ], true)) {
                            $this->subscriptionService->revertLatestUpgradeAfterRefund($subscription);
                        }
                    } catch (\Throwable $exception) {
                        Log::warning('Failed to reconcile refunded billing subscription state.', [
                            'invoice_id' => $invoice->id,
                            'subscription_id' => $subscription->id,
                            'error' => $exception->getMessage(),
                        ]);
                    }
                }
            });
    }

    private function reconcileRefundPaymentStates(): void
    {
        BillingPayment::query()
            ->with(['refunds', 'invoice'])
            ->whereHas('refunds', fn ($query) => $query->where('status', BillingRefund::STATUS_COMPLETED))
            ->chunkById(100, function ($payments) {
                foreach ($payments as $payment) {
                    try {
                        $completedRefundTotal = round((float) $payment->refunds
                            ->where('status', BillingRefund::STATUS_COMPLETED)
                            ->sum('amount'), 2);

                        $targetStatus = $completedRefundTotal >= (float) $payment->amount
                            ? BillingPayment::STATUS_REFUNDED
                            : BillingPayment::STATUS_REFUND_PENDING;

                        if ($payment->status !== $targetStatus) {
                            $payment->forceFill([
                                'status' => $targetStatus,
                            ])->saveOrFail();
                        }

                        if ($payment->invoice) {
                            $this->invoiceService->applyRefundStatus($payment->invoice);
                        }
                    } catch (\Throwable $exception) {
                        Log::warning('Failed to reconcile refunded billing payment state.', [
                            'payment_id' => $payment->id,
                            'error' => $exception->getMessage(),
                        ]);
                    }
                }
            });
    }

    private function resolveSubscriptionForRefundedInvoice(BillingInvoice $invoice): ?BillingSubscription
    {
        if ($invoice->subscription) {
            return $invoice->subscription;
        }

        if ($invoice->order?->id) {
            $subscription = BillingSubscription::query()
                ->where('billing_order_id', $invoice->order->id)
                ->first();
            if ($subscription) {
                return $subscription;
            }
        }

        if ($invoice->order?->server_id) {
            return BillingSubscription::query()
                ->where('server_id', $invoice->order->server_id)
                ->latest('id')
                ->first();
        }

        return null;
    }

    private function reconcileCompletedOrders(CarbonImmutable $now): void
    {
        BillingOrder::query()
            ->with(['server', 'invoice'])
            ->where('order_type', BillingOrder::TYPE_NEW_SERVER)
            ->whereIn('status', [
                BillingOrder::STATUS_PAID,
                BillingOrder::STATUS_QUEUED_PROVISION,
                BillingOrder::STATUS_PROVISIONING,
            ])
            ->whereNotNull('server_id')
            ->chunkById(100, function ($orders) use ($now) {
                foreach ($orders as $order) {
                    if (!$order->server) {
                        continue;
                    }

                    try {
                        $order->forceFill([
                            'status' => BillingOrder::STATUS_PROVISIONED,
                            'provisioned_at' => $order->provisioned_at ?? $now,
                            'provision_failure_code' => null,
                            'provision_failure_message' => null,
                        ])->saveOrFail();

                        $this->subscriptionService->createFromProvisionedOrder($order->fresh(['invoice.payments']));
                    } catch (\Throwable $exception) {
                        Log::warning('Failed to reconcile provisioned billing order state.', [
                            'order_id' => $order->id,
                            'error' => $exception->getMessage(),
                        ]);
                    }
                }
            });
    }

    private function expireOverdueInvoices(CarbonImmutable $now): void
    {
        BillingInvoice::query()
            ->with('order')
            ->whereIn('type', [
                BillingInvoice::TYPE_NEW_SERVER,
                BillingInvoice::TYPE_UPGRADE,
                BillingInvoice::TYPE_MANUAL,
            ])
            ->whereIn('status', [
                BillingInvoice::STATUS_OPEN,
                BillingInvoice::STATUS_DRAFT,
                BillingInvoice::STATUS_FAILED,
                BillingInvoice::STATUS_PROCESSING,
            ])
            ->whereNotNull('due_at')
            ->where('due_at', '<=', $now)
            ->chunkById(100, function ($invoices) {
                foreach ($invoices as $invoice) {
                    try {
                        $this->invoiceService->markExpired(
                            $invoice,
                            'Invoice expired before payment confirmation was completed.'
                        );
                    } catch (\Throwable $exception) {
                        Log::warning('Failed to expire overdue billing invoice.', [
                            'invoice_id' => $invoice->id,
                            'error' => $exception->getMessage(),
                        ]);
                    }
                }
            });
    }

    private function deleteOrphanedSubscriptions(CarbonImmutable $now): void
    {
        BillingSubscription::query()
            ->whereIn('status', BillingSubscription::RESOURCE_RESERVATION_STATUSES)
            ->whereNull('server_id')
            ->chunkById(100, function ($subscriptions) use ($now) {
                foreach ($subscriptions as $subscription) {
                    try {
                        $subscription->forceFill([
                            'status' => BillingSubscription::STATUS_DELETED,
                            'deletion_scheduled_at' => null,
                            'deleted_at' => $subscription->deleted_at ?? $now,
                        ])->saveOrFail();
                    } catch (\Throwable $exception) {
                        Log::warning('Failed to delete orphaned billing subscription.', [
                            'subscription_id' => $subscription->id,
                            'error' => $exception->getMessage(),
                        ]);
                    }
                }
            });
    }

    private function issueRenewalInvoices(CarbonImmutable $now): void
    {
        BillingSubscription::query()
            ->with('user')
            ->whereIn('status', [
                BillingSubscription::STATUS_ACTIVE,
                BillingSubscription::STATUS_PAST_DUE,
            ])
            ->whereNotNull('server_id')
            ->where(function ($query) {
                $query->whereNull('provider_subscription_id')
                    ->orWhere('gateway_provider', '!=', StripeCheckoutService::PROVIDER);
            })
            ->where(function ($query) use ($now) {
                $query->where('next_invoice_at', '<=', $now)
                    ->orWhere(function ($subQuery) use ($now) {
                        $subQuery->whereNull('next_invoice_at')
                            ->where('renews_at', '<=', $now);
                    });
            })
            ->chunkById(100, function ($subscriptions) {
                foreach ($subscriptions as $subscription) {
                    try {
                        $invoice = $this->invoiceService->createRenewalInvoice($subscription, false);

                        $subscription->forceFill([
                            'next_invoice_at' => null,
                        ])->saveOrFail();

                        $this->seedReminderState($invoice);
                    } catch (\Throwable $exception) {
                        Log::warning('Failed to create billing renewal invoice.', [
                            'subscription_id' => $subscription->id,
                            'error' => $exception->getMessage(),
                        ]);
                    }
                }
            });
    }

    private function sendRenewalReminders(CarbonImmutable $now): void
    {
        $offsets = collect(config('billing.renewal_reminder_offsets', [7, 3, 1]))
            ->map(fn ($offset) => (int) $offset)
            ->filter(fn ($offset) => $offset > 0)
            ->unique()
            ->sortDesc()
            ->values()
            ->all();

        BillingInvoice::query()
            ->with(['subscription.user'])
            ->where('type', BillingInvoice::TYPE_RENEWAL)
            ->whereIn('status', [
                BillingInvoice::STATUS_OPEN,
                BillingInvoice::STATUS_DRAFT,
                BillingInvoice::STATUS_FAILED,
                BillingInvoice::STATUS_PROCESSING,
            ])
            ->whereNotNull('due_at')
            ->where('due_at', '>', $now)
            ->chunkById(100, function ($invoices) use ($now, $offsets) {
                foreach ($invoices as $invoice) {
                    if (!$invoice->subscription?->user) {
                        continue;
                    }

                    try {
                        foreach ($offsets as $offset) {
                            if (!$this->shouldSendReminderOffset($invoice, $offset, $now)) {
                                continue;
                            }

                            $invoice->subscription->user->notify(new BillingRenewalReminder($invoice, $offset));
                            $invoice->subscription->forceFill([
                                'renewal_reminder_sent_at' => $now,
                            ])->saveOrFail();
                            $invoice = $this->markReminderOffsetSent($invoice, $offset, $now);
                        }
                    } catch (\Throwable $exception) {
                        Log::warning('Failed to send billing renewal reminder.', [
                            'invoice_id' => $invoice->id,
                            'error' => $exception->getMessage(),
                        ]);
                    }
                }
            });
    }

    private function attemptAutomaticRenewals(CarbonImmutable $now): void
    {
        BillingInvoice::query()
            ->with(['subscription.user'])
            ->where('type', BillingInvoice::TYPE_RENEWAL)
            ->whereIn('status', [
                BillingInvoice::STATUS_OPEN,
                BillingInvoice::STATUS_DRAFT,
                BillingInvoice::STATUS_FAILED,
            ])
            ->whereNotNull('due_at')
            ->where('due_at', '<=', $now)
            ->chunkById(100, function ($invoices) use ($now) {
                foreach ($invoices as $invoice) {
                    $subscription = $invoice->subscription;
                    if (
                        !$subscription
                        || !$subscription->auto_renew
                        || !$subscription->user
                        || $subscription->gateway_provider === StripeCheckoutService::PROVIDER
                    ) {
                        continue;
                    }

                    $state = $this->invoiceState($invoice);
                    if (!empty($state['auto_renew_attempted_at'])) {
                        continue;
                    }

                    try {
                        $invoice = $this->mergeInvoiceState($invoice, [
                            'auto_renew_attempted_at' => $now->toIso8601String(),
                        ]);

                        $this->paymentService->chargeRecurringInvoice($invoice);
                    } catch (\Throwable $exception) {
                        $reason = $exception->getMessage();

                        try {
                            $invoice = $this->invoiceService->markFailed($invoice, $reason);
                            $invoice->subscription?->user?->notify(new BillingPaymentActionRequired($invoice, $reason));
                            $this->mergeInvoiceState($invoice, [
                                'auto_renew_failed_notified_at' => $now->toIso8601String(),
                            ]);
                        } catch (\Throwable $notificationException) {
                            Log::warning('Failed to persist auto-renew fallback state.', [
                                'invoice_id' => $invoice->id,
                                'error' => $notificationException->getMessage(),
                            ]);
                        }
                    }
                }
            });
    }

    private function suspendRefundCancelledSubscriptions(CarbonImmutable $now): void
    {
        BillingSubscription::query()
            ->with('server')
            ->where('status', BillingSubscription::STATUS_CANCELLED)
            ->whereNotNull('server_id')
            ->whereNotNull('grace_suspend_at')
            ->where('grace_suspend_at', '<=', $now)
            ->whereNull('suspended_at')
            ->chunkById(100, function ($subscriptions) use ($now) {
                foreach ($subscriptions as $subscription) {
                    try {
                        if (!$subscription->server) {
                            $subscription->forceFill([
                                'status' => BillingSubscription::STATUS_DELETED,
                                'server_id' => null,
                                'deleted_at' => $now,
                                'deletion_scheduled_at' => null,
                            ])->saveOrFail();

                            continue;
                        }

                        if (!$subscription->server->isSuspended()) {
                            $this->suspensionService->toggle($subscription->server, SuspensionService::ACTION_SUSPEND);
                        }

                        $subscription->forceFill([
                            'suspended_at' => $subscription->suspended_at ?? $now,
                        ])->saveOrFail();
                    } catch (\Throwable $exception) {
                        Log::warning('Failed to suspend refunded billing subscription pending deletion.', [
                            'subscription_id' => $subscription->id,
                            'error' => $exception->getMessage(),
                        ]);
                    }
                }
            });
    }

    private function deleteRefundCancelledSubscriptions(CarbonImmutable $now): void
    {
        BillingSubscription::query()
            ->with('server')
            ->where('status', BillingSubscription::STATUS_CANCELLED)
            ->whereNotNull('grace_delete_at')
            ->where('grace_delete_at', '<=', $now)
            ->chunkById(100, function ($subscriptions) use ($now) {
                foreach ($subscriptions as $subscription) {
                    try {
                        if ($subscription->server) {
                            $this->serverDeletionService->withForce()->handle($subscription->server);
                        }

                        $subscription->forceFill([
                            'status' => BillingSubscription::STATUS_DELETED,
                            'server_id' => null,
                            'deleted_at' => $now,
                            'deletion_scheduled_at' => null,
                        ])->saveOrFail();
                    } catch (\Throwable $exception) {
                        Log::warning('Failed to delete refunded billing subscription after grace period.', [
                            'subscription_id' => $subscription->id,
                            'error' => $exception->getMessage(),
                        ]);
                    }
                }
            });
    }

    private function markPastDueSubscriptions(CarbonImmutable $now): void
    {
        BillingSubscription::query()
            ->with(['user', 'server'])
            ->whereIn('status', [
                BillingSubscription::STATUS_ACTIVE,
                BillingSubscription::STATUS_PAST_DUE,
            ])
            ->whereNotNull('server_id')
            ->chunkById(100, function ($subscriptions) use ($now) {
                foreach ($subscriptions as $subscription) {
                    $invoice = $this->currentOverdueInvoice($subscription, $now);
                    if (!$invoice) {
                        continue;
                    }

                    try {
                        $graceSuspendAt = CarbonImmutable::instance($invoice->due_at)->addHours((int) config('billing.suspend_grace_hours', 24));
                        $graceDeleteAt = CarbonImmutable::instance($invoice->due_at)->addHours((int) config('billing.delete_grace_hours', 72));
                        $shouldIncrementFailures = $subscription->status !== BillingSubscription::STATUS_PAST_DUE
                            && $subscription->status !== BillingSubscription::STATUS_SUSPENDED;

                        $subscription->forceFill([
                            'status' => BillingSubscription::STATUS_PAST_DUE,
                            'failed_payment_count' => $shouldIncrementFailures
                                ? ((int) $subscription->failed_payment_count + 1)
                                : $subscription->failed_payment_count,
                            'grace_suspend_at' => $subscription->grace_suspend_at ?? $graceSuspendAt,
                            'grace_delete_at' => $subscription->grace_delete_at ?? $graceDeleteAt,
                            'deletion_scheduled_at' => $subscription->deletion_scheduled_at ?? $graceDeleteAt,
                        ])->saveOrFail();

                        $state = $this->invoiceState($invoice);
                        if (empty($state['deletion_warning_sent_at'])) {
                            $subscription->user?->notify(new BillingSubscriptionDeletionScheduled($subscription));
                            $this->mergeInvoiceState($invoice, [
                                'deletion_warning_sent_at' => $now->toIso8601String(),
                            ]);
                        }
                    } catch (\Throwable $exception) {
                        Log::warning('Failed to mark subscription as past due.', [
                            'subscription_id' => $subscription->id,
                            'error' => $exception->getMessage(),
                        ]);
                    }
                }
            });
    }

    private function suspendOverdueSubscriptions(CarbonImmutable $now): void
    {
        BillingSubscription::query()
            ->with(['server', 'user'])
            ->whereIn('status', [
                BillingSubscription::STATUS_ACTIVE,
                BillingSubscription::STATUS_PAST_DUE,
            ])
            ->whereNotNull('grace_suspend_at')
            ->where('grace_suspend_at', '<=', $now)
            ->chunkById(100, function ($subscriptions) use ($now) {
                foreach ($subscriptions as $subscription) {
                    $invoice = $this->currentOverdueInvoice($subscription, $now);
                    if (!$invoice) {
                        continue;
                    }

                    try {
                        if (!$subscription->server) {
                            $subscription->forceFill([
                                'status' => BillingSubscription::STATUS_DELETED,
                                'server_id' => null,
                                'deleted_at' => $now,
                            ])->saveOrFail();

                            continue;
                        }

                        if (!$subscription->server->isSuspended()) {
                            $this->suspensionService->toggle($subscription->server, SuspensionService::ACTION_SUSPEND);
                        }

                        $subscription->forceFill([
                            'status' => BillingSubscription::STATUS_SUSPENDED,
                            'suspended_at' => $subscription->suspended_at ?? $now,
                        ])->saveOrFail();

                        $state = $this->invoiceState($invoice);
                        if (empty($state['suspension_sent_at'])) {
                            $subscription->user?->notify(new BillingSubscriptionSuspended($subscription));
                            $this->mergeInvoiceState($invoice, [
                                'suspension_sent_at' => $now->toIso8601String(),
                            ]);
                        }
                    } catch (\Throwable $exception) {
                        Log::warning('Failed to suspend overdue billing subscription.', [
                            'subscription_id' => $subscription->id,
                            'error' => $exception->getMessage(),
                        ]);
                    }
                }
            });
    }

    private function deleteExpiredSubscriptions(CarbonImmutable $now): void
    {
        BillingSubscription::query()
            ->with('server')
            ->whereIn('status', [
                BillingSubscription::STATUS_PAST_DUE,
                BillingSubscription::STATUS_SUSPENDED,
            ])
            ->whereNotNull('grace_delete_at')
            ->where('grace_delete_at', '<=', $now)
            ->chunkById(100, function ($subscriptions) use ($now) {
                foreach ($subscriptions as $subscription) {
                    $invoice = $this->currentOverdueInvoice($subscription, $now);
                    if (!$invoice) {
                        continue;
                    }

                    try {
                        if ($subscription->server) {
                            $this->serverDeletionService->withForce()->handle($subscription->server);
                        }

                        $subscription->forceFill([
                            'status' => BillingSubscription::STATUS_DELETED,
                            'server_id' => null,
                            'deleted_at' => $now,
                            'deletion_scheduled_at' => null,
                        ])->saveOrFail();
                    } catch (\Throwable $exception) {
                        Log::warning('Failed to delete expired billing subscription.', [
                            'subscription_id' => $subscription->id,
                            'error' => $exception->getMessage(),
                        ]);
                    }
                }
            });
    }

    private function currentOverdueInvoice(BillingSubscription $subscription, CarbonImmutable $now): ?BillingInvoice
    {
        return $subscription->invoices()
            ->where('type', BillingInvoice::TYPE_RENEWAL)
            ->whereIn('status', [
                BillingInvoice::STATUS_OPEN,
                BillingInvoice::STATUS_DRAFT,
                BillingInvoice::STATUS_FAILED,
                BillingInvoice::STATUS_PROCESSING,
            ])
            ->whereNotNull('due_at')
            ->where('due_at', '<=', $now)
            ->latest('id')
            ->first();
    }

    private function shouldSendReminderOffset(BillingInvoice $invoice, int $offset, CarbonImmutable $now): bool
    {
        $dueAt = $invoice->due_at ? CarbonImmutable::instance($invoice->due_at) : null;
        if (!$dueAt) {
            return false;
        }

        $state = $this->invoiceState($invoice);
        $sentOffsets = $state['sent_offsets'] ?? [];
        if (in_array($offset, $sentOffsets, true)) {
            return false;
        }

        $windowStart = $dueAt->subDays($offset);
        $windowEnd = $windowStart->addDay();

        return $now->greaterThanOrEqualTo($windowStart) && $now->lessThan($windowEnd);
    }

    private function markReminderOffsetSent(BillingInvoice $invoice, int $offset, CarbonImmutable $now): BillingInvoice
    {
        $state = $this->invoiceState($invoice);
        $sentOffsets = collect($state['sent_offsets'] ?? [])
            ->push($offset)
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->sortDesc()
            ->values()
            ->all();

        return $this->mergeInvoiceState($invoice, [
            'sent_offsets' => $sentOffsets,
            'last_reminder_sent_at' => $now->toIso8601String(),
        ]);
    }

    private function seedReminderState(BillingInvoice $invoice): BillingInvoice
    {
        $state = $this->invoiceState($invoice);
        if (!array_key_exists('sent_offsets', $state)) {
            return $this->mergeInvoiceState($invoice, ['sent_offsets' => []]);
        }

        return $invoice;
    }

    private function invoiceState(BillingInvoice $invoice): array
    {
        return is_array($invoice->reminder_state) ? $invoice->reminder_state : [];
    }

    private function mergeInvoiceState(BillingInvoice $invoice, array $changes): BillingInvoice
    {
        $invoice->forceFill([
            'reminder_state' => array_merge($this->invoiceState($invoice), $changes),
        ])->saveOrFail();

        return $invoice->fresh();
    }
}
