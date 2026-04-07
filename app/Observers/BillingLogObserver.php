<?php

namespace Pterodactyl\Observers;

use Throwable;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;
use Pterodactyl\Models\BillingOrder;
use Pterodactyl\Models\BillingInvoice;
use Pterodactyl\Models\BillingPayment;
use Pterodactyl\Models\BillingGatewayEvent;
use Pterodactyl\Models\BillingPaymentAttempt;
use Pterodactyl\Services\Admin\Logs\AdminLogDiscordService;

class BillingLogObserver
{
    private const DEDUPE_TTL_MINUTES = 720;
    private const CREATE_RELAY_WINDOW_MINUTES = 15;

    public function __construct(private AdminLogDiscordService $discord)
    {
    }

    public function created(Model $model): void
    {
        if (!$this->shouldRelayCreate($model)) {
            return;
        }

        $this->relay($model, 'created');
    }

    public function updated(Model $model): void
    {
        if (!$this->shouldRelayUpdate($model)) {
            return;
        }

        $this->relay($model, 'updated');
    }

    private function relay(Model $model, string $action): void
    {
        if (!$this->claimRelayFingerprint($model, $action)) {
            return;
        }

        try {
            $this->discord->relayPaymentModel($model, $action);
        } catch (Throwable $exception) {
            Log::warning('Failed relaying payment log to Discord.', [
                'model' => $model::class,
                'action' => $action,
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    private function shouldRelayCreate(Model $model): bool
    {
        if (!$this->isFreshCreate($model)) {
            return false;
        }

        return match (true) {
            $model instanceof BillingInvoice => true,
            $model instanceof BillingOrder => true,
            $model instanceof BillingPayment => $this->isMeaningfulPaymentStatus((string) $model->status),
            $model instanceof BillingPaymentAttempt => $this->isMeaningfulAttemptStatus((string) $model->status),
            $model instanceof BillingGatewayEvent => false,
            default => false,
        };
    }

    private function shouldRelayUpdate(Model $model): bool
    {
        return match (true) {
            $model instanceof BillingInvoice => $this->wasAnyChanged($model, [
                'status',
                'provider_status',
                'paid_at',
            ]),
            $model instanceof BillingPayment => $this->wasAnyChanged($model, [
                'status',
                'provider_status',
                'paid_at',
            ]) && $this->isMeaningfulPaymentStatus((string) $model->status),
            $model instanceof BillingPaymentAttempt => $this->wasAnyChanged($model, [
                'status',
                'failure_reason',
            ]) && $this->isMeaningfulAttemptStatus((string) $model->status),
            $model instanceof BillingOrder => $this->wasAnyChanged($model, [
                'status',
                'approved_at',
                'payment_verified_at',
                'provisioned_at',
                'failed_at',
                'provision_failure_message',
            ]),
            $model instanceof BillingGatewayEvent => $model->wasChanged('status')
                && in_array((string) $model->status, [BillingGatewayEvent::STATUS_PROCESSED, BillingGatewayEvent::STATUS_FAILED], true),
            default => false,
        };
    }

    private function wasAnyChanged(Model $model, array $attributes): bool
    {
        foreach ($attributes as $attribute) {
            if ($model->wasChanged($attribute)) {
                return true;
            }
        }

        return false;
    }

    private function isFreshCreate(Model $model): bool
    {
        $createdAt = $model->getAttribute('created_at');
        if (!$createdAt) {
            return true;
        }

        return CarbonImmutable::parse($createdAt)->greaterThanOrEqualTo(
            CarbonImmutable::now()->subMinutes(self::CREATE_RELAY_WINDOW_MINUTES)
        );
    }

    private function isMeaningfulPaymentStatus(string $status): bool
    {
        return in_array($status, [
            BillingPayment::STATUS_CALLBACK_RECEIVED,
            BillingPayment::STATUS_VERIFIED_PAID,
            BillingPayment::STATUS_VERIFIED_FAILED,
            BillingPayment::STATUS_CANCELLED,
            BillingPayment::STATUS_REFUND_PENDING,
            BillingPayment::STATUS_REFUNDED,
            BillingPayment::STATUS_REFUND_FAILED,
        ], true);
    }

    private function isMeaningfulAttemptStatus(string $status): bool
    {
        return in_array($status, [
            BillingPaymentAttempt::STATUS_CALLBACK_RECEIVED,
            BillingPaymentAttempt::STATUS_VERIFIED_PAID,
            BillingPaymentAttempt::STATUS_VERIFIED_FAILED,
            BillingPaymentAttempt::STATUS_CANCELLED,
            BillingPaymentAttempt::STATUS_REFUND_PENDING,
            BillingPaymentAttempt::STATUS_REFUNDED,
            BillingPaymentAttempt::STATUS_REFUND_FAILED,
        ], true);
    }

    private function claimRelayFingerprint(Model $model, string $action): bool
    {
        $key = 'admin_logs:payment:' . sha1(json_encode([
            'class' => $model::class,
            'id' => $model->getKey(),
            'action' => $action,
            'status' => $model->getAttribute('status'),
            'provider_status' => $model->getAttribute('provider_status'),
            'failure_reason' => $model->getAttribute('failure_reason'),
            'processing_error' => $model->getAttribute('processing_error'),
            'provision_failure_message' => $model->getAttribute('provision_failure_message'),
            'paid_at' => optional($model->getAttribute('paid_at'))?->format('c'),
            'processed_at' => optional($model->getAttribute('processed_at'))?->format('c'),
            'approved_at' => optional($model->getAttribute('approved_at'))?->format('c'),
            'payment_verified_at' => optional($model->getAttribute('payment_verified_at'))?->format('c'),
            'provisioned_at' => optional($model->getAttribute('provisioned_at'))?->format('c'),
            'failed_at' => optional($model->getAttribute('failed_at'))?->format('c'),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return Cache::add($key, true, now()->addMinutes(self::DEDUPE_TTL_MINUTES));
    }
}
