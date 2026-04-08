<?php

namespace Pterodactyl\Services\Billing;

use Stripe\Price;
use Stripe\Product;
use Pterodactyl\Exceptions\DisplayException;

class StripePriceService
{
    public function __construct(
        private StripeClientFactory $stripe,
    ) {
    }

    /**
     * @return array{product: Product, price: Price}
     */
    public function createRecurringPrice(string $name, float $amount, array $metadata = []): array
    {
        if ($amount <= 0) {
            throw new DisplayException('Stripe recurring prices must be greater than zero.');
        }

        $client = $this->stripe->make();

        $product = $client->products->create([
            'name' => $name,
            'metadata' => $metadata,
        ]);

        $price = $client->prices->create([
            'currency' => strtolower((string) config('billing.currency', 'MYR')),
            'unit_amount' => (int) round($amount * 100),
            'recurring' => [
                'interval' => 'month',
            ],
            'product' => $product->id,
            'metadata' => $metadata,
        ]);

        return [
            'product' => $product,
            'price' => $price,
        ];
    }
}
