<?php

namespace Pterodactyl\Http\Requests\Admin\Billing;

use Pterodactyl\Http\Requests\Admin\AdminFormRequest;

class BillingGatewaySettingsRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'billing:fiuu:enabled' => 'required|boolean',
            'billing:fiuu:sandbox' => 'required|boolean',
            'billing:fiuu:merchant_id' => 'nullable|string|max:191',
            'billing:fiuu:verify_key' => 'nullable|string|max:191',
            'billing:fiuu:secret_key' => 'nullable|string|max:191',
            'billing:fiuu:return_url' => 'nullable|url|max:255',
            'billing:fiuu:callback_url' => 'nullable|url|max:255',
            'billing:fiuu:requery_url' => 'nullable|url|max:255',
            'billing:fiuu:recurring_url' => 'nullable|url|max:255',
            'billing:fiuu:refund_url' => 'nullable|url|max:255',
            'billing:fiuu:enabled_methods' => 'nullable|string|max:255',
            'billing:currency' => 'required|string|max:8',
            'billing:invoice_lead_days' => 'required|integer|min:1|max:30',
            'billing:suspend_grace_hours' => 'required|integer|min:1|max:720',
            'billing:delete_grace_hours' => 'required|integer|min:1|max:1440',
        ];
    }

    public function normalize(): array
    {
        $data = $this->validated();

        return [
            'billing:fiuu:enabled' => filter_var($data['billing:fiuu:enabled'] ?? false, FILTER_VALIDATE_BOOL),
            'billing:fiuu:sandbox' => filter_var($data['billing:fiuu:sandbox'] ?? false, FILTER_VALIDATE_BOOL),
            'billing:fiuu:merchant_id' => $this->nullableTrim($data['billing:fiuu:merchant_id'] ?? null),
            'billing:fiuu:verify_key' => $this->nullableTrim($data['billing:fiuu:verify_key'] ?? null),
            'billing:fiuu:secret_key' => $this->nullableTrim($data['billing:fiuu:secret_key'] ?? null),
            'billing:fiuu:return_url' => $this->nullableTrim($data['billing:fiuu:return_url'] ?? null),
            'billing:fiuu:callback_url' => $this->nullableTrim($data['billing:fiuu:callback_url'] ?? null),
            'billing:fiuu:requery_url' => $this->nullableTrim($data['billing:fiuu:requery_url'] ?? null),
            'billing:fiuu:recurring_url' => $this->nullableTrim($data['billing:fiuu:recurring_url'] ?? null),
            'billing:fiuu:refund_url' => $this->nullableTrim($data['billing:fiuu:refund_url'] ?? null),
            'billing:fiuu:enabled_methods' => array_values(array_filter(array_map(
                'trim',
                explode(',', (string) ($data['billing:fiuu:enabled_methods'] ?? ''))
            ))),
            'billing:currency' => strtoupper(trim((string) ($data['billing:currency'] ?? 'MYR'))),
            'billing:invoice_lead_days' => (int) ($data['billing:invoice_lead_days'] ?? 7),
            'billing:suspend_grace_hours' => (int) ($data['billing:suspend_grace_hours'] ?? 24),
            'billing:delete_grace_hours' => (int) ($data['billing:delete_grace_hours'] ?? 72),
        ];
    }

    private function nullableTrim(?string $value): ?string
    {
        $value = is_null($value) ? null : trim($value);

        return $value === '' ? null : $value;
    }
}
