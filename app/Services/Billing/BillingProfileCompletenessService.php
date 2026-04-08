<?php

namespace Pterodactyl\Services\Billing;

use Pterodactyl\Models\User;
use Pterodactyl\Models\BillingProfile;
use Pterodactyl\Exceptions\DisplayException;

class BillingProfileCompletenessService
{
    private const REQUIRED_FIELDS = [
        'legal_name',
        'email',
        'phone',
        'address_line_1',
        'city',
        'state',
        'postcode',
        'country_code',
    ];

    private const FIELD_LABELS = [
        'legal_name' => 'Legal Name',
        'email' => 'Invoice Email',
        'phone' => 'Phone',
        'address_line_1' => 'Address Line 1',
        'city' => 'City',
        'state' => 'State',
        'postcode' => 'Postcode',
        'country_code' => 'Country Code',
    ];

    public function __construct(private BillingProfileService $profileService)
    {
    }

    public function forUser(User $user): array
    {
        return $this->assess($this->profileService->getOrCreateForUser($user));
    }

    public function assess(BillingProfile $profile): array
    {
        $snapshot = $this->profileService->snapshot($profile);
        $missing = collect(self::REQUIRED_FIELDS)
            ->filter(function (string $field) use ($snapshot) {
                $value = $snapshot[$field] ?? null;

                return !is_string($value)
                    ? blank($value)
                    : trim($value) === '';
            })
            ->values()
            ->all();

        return [
            'is_complete' => count($missing) < 1,
            'missing_fields' => $missing,
            'required_fields' => self::REQUIRED_FIELDS,
            'missing_labels' => collect($missing)
                ->map(fn (string $field) => self::FIELD_LABELS[$field] ?? $field)
                ->values()
                ->all(),
        ];
    }

    public function assertCompleteForCheckout(User $user): BillingProfile
    {
        $profile = $this->profileService->getOrCreateForUser($user);
        $assessment = $this->assess($profile);

        if ($assessment['is_complete']) {
            return $profile;
        }

        throw new DisplayException(sprintf(
            'Complete your billing details in /account before checkout. Missing: %s.',
            implode(', ', $assessment['missing_labels'])
        ));
    }
}
