<?php

namespace Pterodactyl\Http\Requests\Admin\Logs;

use Pterodactyl\Http\Requests\Admin\AdminFormRequest;

class UpdateAdminLogSettingsRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'admin_logs:new_account:discord_channel_id' => ['nullable', 'regex:/^\d{17,20}$/'],
            'admin_logs:payment:discord_channel_id' => ['nullable', 'regex:/^\d{17,20}$/'],
            'admin_logs:security:discord_channel_id' => ['nullable', 'regex:/^\d{17,20}$/'],
            'admin_logs:login:discord_channel_id' => ['nullable', 'regex:/^\d{17,20}$/'],
            'admin_logs:forgot_password:discord_channel_id' => ['nullable', 'regex:/^\d{17,20}$/'],
            'admin_logs:change_password:discord_channel_id' => ['nullable', 'regex:/^\d{17,20}$/'],
            'admin_logs:change_email:discord_channel_id' => ['nullable', 'regex:/^\d{17,20}$/'],
            'admin_logs:ticket:discord_channel_id' => ['nullable', 'regex:/^\d{17,20}$/'],
        ];
    }
}
