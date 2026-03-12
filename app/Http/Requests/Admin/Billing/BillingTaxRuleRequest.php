<?php

namespace Pterodactyl\Http\Requests\Admin\Billing;

use Pterodactyl\Http\Requests\Admin\AdminFormRequest;

class BillingTaxRuleRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:191',
            'priority' => 'required|integer|min:1|max:100000',
            'country_code' => 'nullable|string|size:2',
            'is_business' => 'nullable|boolean',
            'tax_id_required' => 'nullable|boolean',
            'rate_type' => 'required|string|in:percentage,fixed',
            'rate_value' => 'required|numeric|min:0',
            'apply_to_new_orders' => 'nullable|boolean',
            'apply_to_renewals' => 'nullable|boolean',
            'apply_to_upgrades' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ];
    }

    public function normalize(?array $only = null): array
    {
        $data = $this->validated();
        if (!is_null($only)) {
            $data = array_intersect_key($data, array_flip($only));
        }

        return [
            'name' => trim((string) $data['name']),
            'priority' => (int) $data['priority'],
            'country_code' => $this->normalizeCountryCode($data['country_code'] ?? null),
            'is_business' => $this->normalizeNullableBoolean($data['is_business'] ?? null),
            'tax_id_required' => $this->normalizeNullableBoolean($data['tax_id_required'] ?? null),
            'rate_type' => $data['rate_type'],
            'rate_value' => round((float) $data['rate_value'], 4),
            'apply_to_new_orders' => $this->normalizeBoolean($data['apply_to_new_orders'] ?? false),
            'apply_to_renewals' => $this->normalizeBoolean($data['apply_to_renewals'] ?? false),
            'apply_to_upgrades' => $this->normalizeBoolean($data['apply_to_upgrades'] ?? false),
            'is_active' => $this->normalizeBoolean($data['is_active'] ?? false),
        ];
    }

    private function normalizeCountryCode(?string $value): ?string
    {
        $value = is_null($value) ? null : strtoupper(trim($value));

        return $value === '' ? null : $value;
    }

    private function normalizeBoolean(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOL);
    }

    private function normalizeNullableBoolean(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->normalizeBoolean($value);
    }
}
