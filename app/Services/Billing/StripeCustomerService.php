<?php

namespace Pterodactyl\Services\Billing;

use Throwable;
use Illuminate\Support\Arr;
use Pterodactyl\Models\User;
use Pterodactyl\Models\BillingCustomer;
use Stripe\Customer as StripeCustomer;

class StripeCustomerService
{
    public function __construct(
        private StripeClientFactory $stripe,
    ) {
    }

    public function ensureForUser(User $user, array $overrides = []): BillingCustomer
    {
        $record = BillingCustomer::query()->firstOrNew([
            'user_id' => $user->id,
            'provider' => 'stripe',
        ]);

        if (!$record->provider_customer_id) {
            $customer = $this->stripe->make()->customers->create(array_filter([
                'email' => $user->email,
                'name' => $this->resolveDisplayName($user),
                'phone' => $overrides['phone'] ?? null,
                'address' => Arr::wrap($overrides['address'] ?? []),
                'metadata' => [
                    'local_user_id' => (string) $user->id,
                    'local_user_uuid' => $user->uuid,
                ],
            ], static fn ($value) => !is_null($value) && $value !== []));

            return $this->syncFromStripeCustomer($user, $customer);
        }

        try {
            $customer = $this->stripe->make()->customers->retrieve(
                $record->provider_customer_id,
                ['expand' => ['invoice_settings.default_payment_method', 'tax_ids']]
            );
        } catch (Throwable) {
            $customer = $this->stripe->make()->customers->update($record->provider_customer_id, array_filter([
                'email' => $user->email,
                'name' => $this->resolveDisplayName($user),
            ], static fn ($value) => !is_null($value) && $value !== ''));
        }

        if (($overrides['phone'] ?? null) || ($overrides['address'] ?? null)) {
            $customer = $this->stripe->make()->customers->update($record->provider_customer_id, array_filter([
                'phone' => $overrides['phone'] ?? null,
                'address' => Arr::wrap($overrides['address'] ?? []),
            ], static fn ($value) => !is_null($value) && $value !== []));
        }

        return $this->syncFromStripeCustomer($user, $customer);
    }

    public function syncByProviderCustomerId(string $providerCustomerId, ?User $user = null): ?BillingCustomer
    {
        if ($providerCustomerId === '') {
            return null;
        }

        $customer = $this->stripe->make()->customers->retrieve(
            $providerCustomerId,
            ['expand' => ['invoice_settings.default_payment_method', 'tax_ids']]
        );

        $user ??= User::query()->find((int) Arr::get($customer->metadata?->toArray() ?? [], 'local_user_id'));
        if (!$user) {
            return null;
        }

        return $this->syncFromStripeCustomer($user, $customer);
    }

    public function syncFromStripeCustomer(User $user, StripeCustomer $customer): BillingCustomer
    {
        $payload = $customer->toArray();
        $paymentMethod = Arr::get($payload, 'invoice_settings.default_payment_method', []);

        return BillingCustomer::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'provider' => 'stripe',
            ],
            [
                'provider_customer_id' => $customer->id,
                'email_snapshot' => Arr::get($payload, 'email', $user->email),
                'name_snapshot' => Arr::get($payload, 'name', $this->resolveDisplayName($user)),
                'phone_snapshot' => Arr::get($payload, 'phone'),
                'address_snapshot' => Arr::get($payload, 'address', []),
                'tax_ids_snapshot' => Arr::get($payload, 'tax_ids.data', []),
                'default_payment_method_type' => Arr::get($paymentMethod, 'type'),
                'default_payment_method_brand' => Arr::get($paymentMethod, 'card.brand'),
                'default_payment_method_last4' => Arr::get($paymentMethod, 'card.last4'),
                'portal_ready_at' => now(),
            ]
        );
    }

    public function buildInvoiceSnapshot(User $user, array $details = []): array
    {
        $address = Arr::wrap($details['address'] ?? []);

        return [
            'legal_name' => $details['name'] ?? $this->resolveDisplayName($user),
            'company_name' => Arr::get($details, 'company'),
            'email' => $details['email'] ?? $user->email,
            'phone' => Arr::get($details, 'phone'),
            'address_line_1' => Arr::get($address, 'line1'),
            'address_line_2' => Arr::get($address, 'line2'),
            'city' => Arr::get($address, 'city'),
            'state' => Arr::get($address, 'state'),
            'postcode' => Arr::get($address, 'postal_code'),
            'country_code' => Arr::get($address, 'country'),
            'tax_ids' => Arr::wrap($details['tax_ids'] ?? []),
            'provider' => 'stripe',
        ];
    }

    private function resolveDisplayName(User $user): string
    {
        $name = trim((string) ($user->name ?? ''));

        return $name !== '' ? $name : $user->username;
    }
}
