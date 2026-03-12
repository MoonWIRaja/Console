<?php

namespace Pterodactyl\Http\Requests\Admin\Billing;

use Illuminate\Validation\Validator;
use Pterodactyl\Http\Requests\Admin\AdminFormRequest;

class BillingGatewaySettingsRequest extends AdminFormRequest
{
    private const DEFAULT_REQUERY_URL = 'https://api.fiuu.com/RMS/API/gate-query/index.php';
    private const DEFAULT_RECURRING_URL = 'https://pay.fiuu.com/RMS/API/token/index.php';
    private const DEFAULT_REFUND_URL = 'https://api.fiuu.com/RMS/API/refundAPI/index.php';

    public function rules(): array
    {
        return [
            'billing:fiuu:enabled' => 'required|boolean',
            'billing:fiuu:sandbox' => 'required|boolean',
            'billing:fiuu:merchant_id' => 'nullable|string|max:191',
            'billing:fiuu:verify_key' => 'nullable|string|max:191',
            'billing:fiuu:extended_vcode' => 'required|boolean',
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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $sandbox = filter_var($this->input('billing:fiuu:sandbox'), FILTER_VALIDATE_BOOL);
            $merchantId = strtoupper(trim((string) $this->input('billing:fiuu:merchant_id', '')));

            if ($merchantId === '') {
                return;
            }

            if ($sandbox && !str_starts_with($merchantId, 'SB_')) {
                $validator->errors()->add(
                    'billing:fiuu:merchant_id',
                    'Sandbox mode requires a Fiuu sandbox Merchant ID that begins with "SB_".'
                );
            }

            if (!$sandbox && str_starts_with($merchantId, 'SB_')) {
                $validator->errors()->add(
                    'billing:fiuu:merchant_id',
                    'Production mode cannot use a sandbox Merchant ID. Disable sandbox mode or use your live Merchant ID.'
                );
            }
        });
    }

    public function normalize(?array $only = null): array
    {
        $data = $this->validated();
        if (!is_null($only)) {
            $data = array_intersect_key($data, array_flip($only));
        }

        $returnUrl = $this->nullableTrim($data['billing:fiuu:return_url'] ?? null) ?? route('billing.gateway.fiuu.return');
        $callbackUrl = $this->nullableTrim($data['billing:fiuu:callback_url'] ?? null) ?? route('billing.gateway.fiuu.callback');
        $requeryUrl = $this->nullableTrim($data['billing:fiuu:requery_url'] ?? null) ?? self::DEFAULT_REQUERY_URL;
        $recurringUrl = $this->nullableTrim($data['billing:fiuu:recurring_url'] ?? null) ?? self::DEFAULT_RECURRING_URL;
        $refundUrl = $this->nullableTrim($data['billing:fiuu:refund_url'] ?? null) ?? self::DEFAULT_REFUND_URL;

        return [
            'billing:fiuu:enabled' => filter_var($data['billing:fiuu:enabled'] ?? false, FILTER_VALIDATE_BOOL),
            'billing:fiuu:sandbox' => filter_var($data['billing:fiuu:sandbox'] ?? false, FILTER_VALIDATE_BOOL),
            'billing:fiuu:merchant_id' => $this->nullableTrim($data['billing:fiuu:merchant_id'] ?? null),
            'billing:fiuu:verify_key' => $this->nullableTrim($data['billing:fiuu:verify_key'] ?? null),
            'billing:fiuu:extended_vcode' => filter_var($data['billing:fiuu:extended_vcode'] ?? false, FILTER_VALIDATE_BOOL),
            'billing:fiuu:secret_key' => $this->nullableTrim($data['billing:fiuu:secret_key'] ?? null),
            'billing:fiuu:return_url' => $returnUrl,
            'billing:fiuu:callback_url' => $callbackUrl,
            'billing:fiuu:requery_url' => $requeryUrl,
            'billing:fiuu:recurring_url' => $recurringUrl,
            'billing:fiuu:refund_url' => $refundUrl,
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
