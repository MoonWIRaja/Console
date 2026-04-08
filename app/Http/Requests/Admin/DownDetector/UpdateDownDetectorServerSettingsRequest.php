<?php

namespace Pterodactyl\Http\Requests\Admin\DownDetector;

use Pterodactyl\Http\Requests\Admin\AdminFormRequest;

class UpdateDownDetectorServerSettingsRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'monitor_servers' => 'required|boolean',
            'server.discord.alert_channel_id' => ['nullable', 'regex:/^\d{5,32}$/'],
            'server.discord.launcher_channel_id' => ['nullable', 'regex:/^\d{5,32}$/'],
            'server.auto_restart_default_enabled' => 'required|boolean',
            'server.auto_restart_delay_seconds' => 'required|integer|min:10|max:600',
            'server.auto_restart_max_attempts' => 'required|integer|min:1|max:20',
            'server.auto_restart_window_minutes' => 'required|integer|min:1|max:1440',
        ];
    }
}
