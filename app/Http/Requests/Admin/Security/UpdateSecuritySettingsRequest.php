<?php

namespace Pterodactyl\Http\Requests\Admin\Security;

use Pterodactyl\Http\Requests\Admin\AdminFormRequest;

class UpdateSecuritySettingsRequest extends AdminFormRequest
{
    private const SECRET_KEYS = [
        'security:break_glass:emergency_token',
    ];

    public function rules(): array
    {
        return [
            'security:enabled' => 'required|in:true,false',
            'security:trusted_networks' => 'nullable|string|max:4096',
            'security:break_glass:trusted_networks' => 'nullable|string|max:4096',
            'security:break_glass:emergency_token' => 'nullable|string|max:2048',
            'security:runtime:ip_deny_minutes' => 'required|integer|between:1,1440',
            'security:runtime:fingerprint_deny_minutes' => 'required|integer|between:1,1440',
            'security:runtime:route_hold_minutes' => 'required|integer|between:1,1440',
            'security:auth:enabled' => 'required|in:true,false',
            'security:auth:auto_challenge' => 'required|in:true,false',
            'security:auth:auto_temp_block' => 'required|in:true,false',
            'security:api:enabled' => 'required|in:true,false',
            'security:api:revoke_token_on_ip_anomaly' => 'required|in:true,false',
            'security:bridge:enabled' => 'required|in:true,false',
            'security:webhook:enabled' => 'required|in:true,false',
            'security:upload:enabled' => 'required|in:true,false',
            'security:upload:quarantine_on_suspicious' => 'required|in:true,false',
            'security:upload:suspicious_extensions' => 'nullable|string|max:4096',
            'security:origin:enabled' => 'required|in:true,false',
            'security:origin:not_controllable_threshold' => 'required|integer|between:100,100000000',
            'security:agent:enabled' => 'required|in:true,false',
            'security:agent:heartbeat_ttl_seconds' => 'required|integer|between:60,86400',
            'security:agent:clock_skew_seconds' => 'required|integer|between:5,3600',
            'security:agent:secret_rotation_grace_seconds' => 'required|integer|between:60,604800',
            'security:agent:nonce_ttl_seconds' => 'required|integer|between:30,3600',
            'security:retention:event_days' => 'required|integer|between:1,3650',
            'security:retention:incident_days' => 'required|integer|between:1,3650',
        ];
    }

    public function normalize(?array $only = null): array
    {
        $values = parent::normalize($only);

        foreach ([
            'security:trusted_networks',
            'security:break_glass:trusted_networks',
            'security:upload:suspicious_extensions',
        ] as $key) {
            $values[$key] = array_values(array_filter(array_map(
                'trim',
                explode(',', (string) ($values[$key] ?? ''))
            )));
        }

        foreach (self::SECRET_KEYS as $key) {
            $value = $values[$key] ?? null;
            if (!is_string($value) || trim($value) === '') {
                unset($values[$key]);
            }
        }

        return $values;
    }
}
