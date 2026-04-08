<?php

namespace Pterodactyl\Http\Requests\Admin\DownDetector;

use Pterodactyl\Http\Requests\Admin\AdminFormRequest;

class UpdateDownDetectorSettingsRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'enabled' => 'required|boolean',
            'interval_seconds' => 'required|integer|min:60|max:3600',
            'probe_timeout_ms' => 'required|integer|min:1000|max:30000',
            'failure_threshold' => 'required|integer|min:1|max:10',
            'recovery_threshold' => 'required|integer|min:1|max:10',
        ];
    }
}
