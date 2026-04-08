<?php

namespace Pterodactyl\Services\Billing;

use Pterodactyl\Models\User;
use Pterodactyl\Exceptions\DisplayException;

class StripePortalService
{
    public function __construct(
        private StripeClientFactory $stripe,
        private StripeCustomerService $customerService,
    ) {
    }

    public function createSession(User $user): string
    {
        $customer = $this->customerService->ensureForUser($user);
        if (!$customer->provider_customer_id) {
            throw new DisplayException('Stripe customer record could not be prepared.');
        }

        $params = [
            'customer' => $customer->provider_customer_id,
            'return_url' => rtrim((string) config('app.url', ''), '/') . '/billing',
        ];

        $configurationId = trim((string) config('billing.stripe.portal_configuration_id', ''));
        if ($configurationId !== '') {
            $params['configuration'] = $configurationId;
        }

        $session = $this->stripe->make()->billingPortal->sessions->create($params);

        return $session->url;
    }
}
