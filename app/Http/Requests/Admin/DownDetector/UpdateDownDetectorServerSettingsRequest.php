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
        ];
    }
}
