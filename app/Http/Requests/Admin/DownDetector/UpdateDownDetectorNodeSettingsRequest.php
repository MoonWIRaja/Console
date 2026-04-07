<?php

namespace Pterodactyl\Http\Requests\Admin\DownDetector;

use Pterodactyl\Http\Requests\Admin\AdminFormRequest;

class UpdateDownDetectorNodeSettingsRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'monitor_nodes' => 'required|boolean',
            'node.discord.alert_channel_id' => ['nullable', 'regex:/^\d{5,32}$/'],
            'node.periodic_reports_enabled' => 'required|boolean',
            'node.periodic_report_interval_minutes' => 'required|integer|min:60|max:10080',
        ];
    }
}
