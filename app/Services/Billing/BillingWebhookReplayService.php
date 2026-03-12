<?php

namespace Pterodactyl\Services\Billing;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Models\BillingGatewayEvent;

class BillingWebhookReplayService
{
    public function __construct(private BillingPaymentService $paymentService)
    {
    }

    public function replay(BillingGatewayEvent $event): array
    {
        return match ($event->provider) {
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

        BillingGatewayEvent::query()
            ->where('provider', FiuuCheckoutService::PROVIDER)
            ->whereIn('status', [
                BillingGatewayEvent::STATUS_RECEIVED,
                BillingGatewayEvent::STATUS_FAILED,
            ])
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
