<?php

namespace Pterodactyl\Services\Billing;

use Stripe\StripeClient;
use Pterodactyl\Exceptions\DisplayException;

class StripeClientFactory
{
    public function make(): StripeClient
    {
        if (!$this->isEnabled()) {
            throw new DisplayException('Stripe billing is not enabled yet.');
        }

        $secretKey = (string) config('billing.stripe.secret_key', '');
        if ($secretKey === '') {
            throw new DisplayException('Stripe secret key has not been configured.');
        }

        $config = ['api_key' => $secretKey];
        $apiVersion = trim((string) config('billing.stripe.api_version', ''));
        if ($apiVersion !== '') {
            $config['stripe_version'] = $apiVersion;
        }

        return new StripeClient($config);
    }

    public function isEnabled(): bool
    {
        return (bool) config('billing.stripe.enabled', false);
    }

    public function publishableKey(): ?string
    {
        $key = trim((string) config('billing.stripe.publishable_key', ''));

        return $key === '' ? null : $key;
    }

    public function webhookSecret(): ?string
    {
        $secret = trim((string) config('billing.stripe.webhook_secret', ''));

        return $secret === '' ? null : $secret;
    }
}
