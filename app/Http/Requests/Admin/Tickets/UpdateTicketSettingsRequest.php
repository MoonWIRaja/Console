<?php

namespace Pterodactyl\Http\Requests\Admin\Tickets;

use Illuminate\Validation\Validator;
use Pterodactyl\Http\Requests\Admin\AdminFormRequest;

class UpdateTicketSettingsRequest extends AdminFormRequest
{
    private const SECRET_KEYS = [
        'services:discord:bot_token',
        'tickets:discord:relay_webhook_token',
        'tickets:bridge:shared_secret',
    ];

    public function rules(): array
    {
        return [
            'tickets:enabled' => 'required|in:true,false',
            'tickets:auto_create_on_manual_checkout' => 'required|in:true,false',
            'tickets:resolve_on_paid' => 'required|in:true,false',
            'services:discord:application_id' => ['nullable', 'regex:/^\d{17,20}$/'],
            'services:discord:application_public_key' => 'nullable|string|max:191',
            'services:discord:guild_id' => ['nullable', 'regex:/^\d{17,20}$/'],
            'services:discord:bot_token' => 'nullable|string|max:2048',
            'tickets:discord:launcher_channel_id' => ['nullable', 'regex:/^\d{17,20}$/'],
            'tickets:discord:active_parent_channel_id' => ['nullable', 'regex:/^\d{17,20}$/'],
            'tickets:discord:log_channel_id' => ['nullable', 'regex:/^\d{17,20}$/'],
            'tickets:discord:staff_role_ids' => 'nullable|string|max:2048',
            'tickets:discord:launcher_message_id' => ['nullable', 'regex:/^\d{17,20}$/'],
            'tickets:discord:relay_webhook_id' => ['nullable', 'regex:/^\d{17,20}$/'],
            'tickets:discord:relay_webhook_token' => 'nullable|string|max:2048',
            'tickets:bridge:shared_secret' => 'nullable|string|max:2048',
            'tickets:attachments:max_files_per_message' => 'required|integer|between:1,10',
            'tickets:attachments:max_file_size_mb' => 'required|integer|between:1,100',
        ];
    }

    public function normalize(?array $only = null): array
    {
        $values = parent::normalize($only);

        foreach (self::SECRET_KEYS as $key) {
            $value = $values[$key] ?? null;
            if (!is_string($value) || trim($value) === '') {
                unset($values[$key]);
            }
        }

        if (isset($values['tickets:discord:staff_role_ids'])) {
            $values['tickets:discord:staff_role_ids'] = array_values(array_filter(array_map(
                'trim',
                explode(',', (string) $values['tickets:discord:staff_role_ids'])
            )));
        }

        return $values;
    }

    public function withValidator($validator): void
    {
        $validator->after(function (Validator $validator) {
            if (!$this->booleanInput('tickets:enabled')) {
                return;
            }

            foreach ([
                'services:discord:guild_id' => 'Discord guild ID',
                'services:discord:bot_token' => 'Discord bot token',
                'tickets:discord:launcher_channel_id' => 'launcher channel ID',
                'tickets:discord:active_parent_channel_id' => 'active parent channel ID',
                'tickets:discord:log_channel_id' => 'log channel ID',
                'tickets:bridge:shared_secret' => 'bridge shared secret',
            ] as $key => $label) {
                if (!$this->filledInput($key)) {
                    $validator->errors()->add($key, "A {$label} is required when ticketing is enabled.");
                }
            }
        });
    }

    private function booleanInput(string $key): bool
    {
        return filter_var($this->input($key, 'false'), FILTER_VALIDATE_BOOLEAN);
    }

    private function filledInput(string $key): bool
    {
        $value = trim((string) $this->input($key, ''));
        if ($value !== '') {
            return true;
        }

        if (in_array($key, self::SECRET_KEYS, true)) {
            return filled(config(str_replace(':', '.', $key)));
        }

        return filled(config(str_replace(':', '.', $key)));
    }
}
