<?php

namespace Pterodactyl\Http\Requests\Admin\Billing;

use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Pterodactyl\Models\BillingCoupon;
use Pterodactyl\Http\Requests\Admin\AdminFormRequest;

class BillingCouponRequest extends AdminFormRequest
{
    /**
     * Preset options shown in the expiry dropdown, in months. "never" (no key here)
     * is the absence of a value and simply leaves expires_at null.
     */
    public const EXPIRY_MONTH_OPTIONS = [1, 2, 3, 6, 12];

    public function rules(): array
    {
        $couponId = $this->route('billingCoupon')?->id;

        return [
            'code' => [
                'nullable',
                'string',
                'max:64',
                'regex:/^[A-Za-z0-9._-]+$/',
                Rule::unique('billing_coupons', 'code')->ignore($couponId),
            ],
            'description' => 'nullable|string|max:191',
            'discount_type' => 'required|string|in:percentage,fixed',
            'discount_value' => 'required|numeric|min:0',
            // 0 (or blank) means unlimited - there is no separate limit_type toggle anymore.
            'max_redemptions' => 'nullable|integer|min:0',
            // "keep" only makes sense on the edit form (pre-selected there when the
            // coupon already has an expiry) - normalize() drops the key entirely for
            // it so an update never touches an expiry the admin didn't mean to change.
            'expiry_months' => ['nullable', 'string', Rule::in(array_merge(['never', 'keep'], array_map('strval', self::EXPIRY_MONTH_OPTIONS)))],
            'is_active' => 'nullable|boolean',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->input('discount_type') === BillingCoupon::TYPE_PERCENTAGE
                && (float) $this->input('discount_value') > 100) {
                $validator->errors()->add('discount_value', 'A percentage discount cannot be greater than 100%.');
            }
        });
    }

    public function normalize(?array $only = null): array
    {
        $data = $this->validated();

        $type = $data['discount_type'];
        $value = round((float) $data['discount_value'], 2);
        if ($type === BillingCoupon::TYPE_PERCENTAGE) {
            $value = min($value, 100);
        }

        $maxRedemptions = (int) ($data['max_redemptions'] ?? 0);

        $normalized = [
            'code' => $this->normalizeCode($data['code'] ?? null),
            'description' => $this->normalizeDescription($data['description'] ?? null),
            'discount_type' => $type,
            'discount_value' => $value,
            'max_redemptions' => $maxRedemptions > 0 ? $maxRedemptions : null,
            'is_active' => filter_var($data['is_active'] ?? false, FILTER_VALIDATE_BOOL),
        ];

        $expiryMonths = $data['expiry_months'] ?? 'never';
        if ($expiryMonths !== 'keep') {
            $normalized['expires_at'] = $this->resolveExpiresAt($expiryMonths);
        }

        return $normalized;
    }

    public function resolveExpiresAt(?string $expiryMonths): ?CarbonImmutable
    {
        if (!$expiryMonths || $expiryMonths === 'never') {
            return null;
        }

        return CarbonImmutable::now()->addMonthsNoOverflow((int) $expiryMonths);
    }

    private function normalizeCode(?string $value): ?string
    {
        $value = strtoupper(trim((string) $value));

        return $value === '' ? null : $value;
    }

    private function normalizeDescription(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
