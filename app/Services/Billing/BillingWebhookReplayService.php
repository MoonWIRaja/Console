<?php

namespace Pterodactyl\Services\Billing;

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
}
