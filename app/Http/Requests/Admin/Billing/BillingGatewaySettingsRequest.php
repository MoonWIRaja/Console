<?php

namespace Pterodactyl\Http\Requests\Admin\Billing;

use Pterodactyl\Http\Requests\Admin\AdminFormRequest;

class BillingGatewaySettingsRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'billing:stripe:enabled' => 'required|boolean',
            'billing:stripe:mode' => 'required|string|in:test,live',
            'billing:stripe:publishable_key' => 'nullable|string|max:255',
            'billing:stripe:secret_key' => 'nullable|string|max:255',
            'billing:stripe:webhook_secret' => 'nullable|string|max:255',
            'billing:stripe:portal_configuration_id' => 'nullable|string|max:255',
            'billing:stripe:automatic_tax_enabled' => 'required|boolean',
            'billing:stripe:success_url' => 'nullable|url|max:255',
            'billing:stripe:cancel_url' => 'nullable|url|max:255',
            'billing:currency' => 'required|string|max:8',
            'billing:invoice_lead_days' => 'required|integer|min:1|max:30',
            'billing:suspend_grace_hours' => 'required|integer|min:1|max:720',
            'billing:delete_grace_hours' => 'required|integer|min:1|max:1440',
            'billing:refund_suspend_hours' => 'required|integer|min:1|max:720',
            'billing:refund_delete_after_suspend_hours' => 'required|integer|min:1|max:1440',
        ];
    }

    public function normalize(?array $only = null): array
    {
        $data = $this->validated();
        if (!is_null($only)) {
            $data = array_intersect_key($data, array_flip($only));
        }

        $successUrl = $this->nullableTrim($data['billing:stripe:success_url'] ?? null)
            ?? route('billing.gateway.stripe.return');
        $cancelUrl = $this->nullableTrim($data['billing:stripe:cancel_url'] ?? null)
            ?? rtrim((string) config('app.url', ''), '/') . '/billing';

        return [
            'billing:stripe:enabled' => filter_var($data['billing:stripe:enabled'] ?? false, FILTER_VALIDATE_BOOL),
            'billing:stripe:mode' => strtolower(trim((string) ($data['billing:stripe:mode'] ?? 'test'))),
            'billing:stripe:publishable_key' => $this->nullableTrim($data['billing:stripe:publishable_key'] ?? null),
            'billing:stripe:secret_key' => $this->nullableTrim($data['billing:stripe:secret_key'] ?? null),
            'billing:stripe:webhook_secret' => $this->nullableTrim($data['billing:stripe:webhook_secret'] ?? null),
            'billing:stripe:portal_configuration_id' => $this->nullableTrim($data['billing:stripe:portal_configuration_id'] ?? null),
            'billing:stripe:automatic_tax_enabled' => filter_var($data['billing:stripe:automatic_tax_enabled'] ?? false, FILTER_VALIDATE_BOOL),
            'billing:stripe:success_url' => $successUrl,
            'billing:stripe:cancel_url' => $cancelUrl,
            'billing:currency' => strtoupper(trim((string) ($data['billing:currency'] ?? 'MYR'))),
            'billing:invoice_lead_days' => (int) ($data['billing:invoice_lead_days'] ?? 7),
            'billing:suspend_grace_hours' => (int) ($data['billing:suspend_grace_hours'] ?? 24),
            'billing:delete_grace_hours' => (int) ($data['billing:delete_grace_hours'] ?? 72),
            'billing:refund_suspend_hours' => (int) ($data['billing:refund_suspend_hours'] ?? 5),
            'billing:refund_delete_after_suspend_hours' => (int) ($data['billing:refund_delete_after_suspend_hours'] ?? 24),
        ];
    }

    private function nullableTrim(?string $value): ?string
    {
        $value = is_null($value) ? null : trim($value);

        return $value === '' ? null : $value;
    }
}
