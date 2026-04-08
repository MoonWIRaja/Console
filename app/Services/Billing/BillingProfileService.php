<?php

namespace Pterodactyl\Services\Billing;

use Pterodactyl\Models\User;
use Pterodactyl\Models\BillingProfile;

class BillingProfileService
{
    public function getOrCreateForUser(User $user): BillingProfile
    {
        return BillingProfile::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'legal_name' => $user->name,
                'company_name' => null,
                'email' => $user->email,
                'phone' => null,
                'address_line_1' => null,
                'address_line_2' => null,
                'city' => null,
                'state' => null,
                'postcode' => null,
                'country_code' => 'MY',
                'tax_id' => null,
                'is_business' => false,
            ]
        );
    }

    public function update(User $user, array $data): BillingProfile
    {
        $profile = $this->getOrCreateForUser($user);
        $profile->fill([
            'legal_name' => $data['legal_name'] ?? $profile->legal_name ?? $user->name,
            'company_name' => $data['company_name'] ?? null,
            'email' => $data['email'] ?? $profile->email ?? $user->email,
            'phone' => $data['phone'] ?? null,
            'address_line_1' => $data['address_line_1'] ?? null,
            'address_line_2' => $data['address_line_2'] ?? null,
            'city' => $data['city'] ?? null,
            'state' => $data['state'] ?? null,
            'postcode' => $data['postcode'] ?? null,
            'country_code' => strtoupper((string) ($data['country_code'] ?? $profile->country_code ?? 'MY')),
            'tax_id' => $data['tax_id'] ?? null,
            'is_business' => (bool) ($data['is_business'] ?? false),
        ])->saveOrFail();

        return $profile->fresh();
    }

    public function snapshot(BillingProfile $profile): array
    {
        return [
            'user_id' => $profile->user_id,
            'legal_name' => $profile->legal_name,
            'company_name' => $profile->company_name,
            'email' => $profile->email,
            'phone' => $profile->phone,
            'address_line_1' => $profile->address_line_1,
            'address_line_2' => $profile->address_line_2,
            'city' => $profile->city,
            'state' => $profile->state,
            'postcode' => $profile->postcode,
            'country_code' => $profile->country_code,
            'tax_id' => $profile->tax_id,
            'is_business' => $profile->is_business,
        ];
    }
}
