<?php

namespace Pterodactyl\Services\Billing;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Models\BillingGatewayEvent;

class BillingWebhookReplayService
{
    private const AUTO_REPLAY_WINDOW_MINUTES = 180;

    public function __construct(private BillingPaymentService $paymentService)
    {
    }

    public function replay(BillingGatewayEvent $event): array
    {
        return match ($event->provider) {
            BclCheckoutService::PROVIDER => $this->paymentService->handleBclWebhook($event->payload ?? [], true),
            FiuuCheckoutService::PROVIDER => $this->paymentService->handleFiuuCallback($event->payload ?? [], true),
            default => [
                'processed' => false,
                'message' => 'No replay handler exists for the configured gateway provider.',
            ],
        };
    }

    public function replayPending(int $limit = 25): array
    {
        $results = [];
        $cutoff = CarbonImmutable::now()->subMinutes(self::AUTO_REPLAY_WINDOW_MINUTES);

        BillingGatewayEvent::query()
            ->whereIn('provider', [
                BclCheckoutService::PROVIDER,
                FiuuCheckoutService::PROVIDER,
            ])
            ->where('created_at', '>=', $cutoff)
            ->where(function ($query) {
                $query->where('status', BillingGatewayEvent::STATUS_RECEIVED)
                    ->orWhere(function ($failed) {
                        $failed->where('status', BillingGatewayEvent::STATUS_FAILED)
                            ->whereNull('processed_at');
                    });
            })
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->each(function (BillingGatewayEvent $event) use (&$results) {
                try {
                    $results[] = [
                        'event_id' => $event->id,
                        'result' => $this->replay($event),
                    ];
                } catch (\Throwable $exception) {
                    Log::warning('Automatic billing gateway event replay failed.', [
                        'event_id' => $event->id,
                        'provider' => $event->provider,
                        'error' => $exception->getMessage(),
                    ]);

                    $event->forceFill([
                        'status' => BillingGatewayEvent::STATUS_FAILED,
                        'processing_error' => $exception->getMessage(),
                    ])->save();

                    $results[] = [
                        'event_id' => $event->id,
                        'result' => [
                            'processed' => false,
                            'message' => $exception->getMessage(),
                        ],
                    ];
                }
            });

        return $results;
    }
}
