<?php

namespace Pterodactyl\Http\Requests\Admin\Billing;

use Pterodactyl\Http\Requests\Admin\AdminFormRequest;

class BillingGatewaySettingsRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'billing:gateway:default' => 'required|string|in:manual,bcl',
            'billing:gateway:manual_mode' => 'required|boolean',
            'billing:manual:enabled' => 'required|boolean',
            'billing:bcl:enabled' => 'required|boolean',
            'billing:bcl:form_url' => 'nullable|url|max:2048',
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

        return [
            'billing:gateway:default' => in_array(($data['billing:gateway:default'] ?? 'manual'), ['manual', 'bcl'], true)
                ? (string) $data['billing:gateway:default']
                : 'manual',
            'billing:gateway:manual_mode' => filter_var($data['billing:gateway:manual_mode'] ?? true, FILTER_VALIDATE_BOOL),
            'billing:manual:enabled' => filter_var($data['billing:manual:enabled'] ?? true, FILTER_VALIDATE_BOOL),
            'billing:bcl:enabled' => filter_var($data['billing:bcl:enabled'] ?? false, FILTER_VALIDATE_BOOL),
            'billing:bcl:form_url' => ($url = trim((string) ($data['billing:bcl:form_url'] ?? ''))) !== '' ? $url : null,
            'billing:currency' => strtoupper(trim((string) ($data['billing:currency'] ?? 'MYR'))),
            'billing:invoice_lead_days' => (int) ($data['billing:invoice_lead_days'] ?? 7),
            'billing:suspend_grace_hours' => (int) ($data['billing:suspend_grace_hours'] ?? 24),
            'billing:delete_grace_hours' => (int) ($data['billing:delete_grace_hours'] ?? 72),
            'billing:refund_suspend_hours' => (int) ($data['billing:refund_suspend_hours'] ?? 5),
            'billing:refund_delete_after_suspend_hours' => (int) ($data['billing:refund_delete_after_suspend_hours'] ?? 24),
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $default = (string) $this->input('billing:gateway:default', 'manual');
            $manualMode = filter_var($this->input('billing:gateway:manual_mode', true), FILTER_VALIDATE_BOOL);
            $bclEnabled = filter_var($this->input('billing:bcl:enabled', false), FILTER_VALIDATE_BOOL);
            $bclFormUrl = trim((string) $this->input('billing:bcl:form_url', ''));

            if ($default === 'bcl' && !$bclEnabled) {
                $validator->errors()->add('billing:bcl:enabled', 'Enable the BCL provider before setting it as the default billing provider.');
            }

            if ($default === 'bcl' && !$manualMode && $bclFormUrl === '') {
                $validator->errors()->add('billing:bcl:form_url', 'BCL form URL is required when BCL hosted checkout is active.');
            }
        });
    }
}
