<?php

namespace Pterodactyl\Http\Requests\Admin\Settings;

use Pterodactyl\Http\Requests\Admin\AdminFormRequest;

class UpdateAlwaysMotdSettingsRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'detection.nestNamesInput' => 'nullable|string|max:2048',
            'detection.nestIdsInput' => ['nullable', 'regex:/^\s*\d+(?:\s*,\s*\d+)*\s*$|^$/'],
            'detection.eggNamesInput' => 'nullable|string|max:2048',
            'detection.eggIdsInput' => ['nullable', 'regex:/^\s*\d+(?:\s*,\s*\d+)*\s*$|^$/'],
            'excludeEggsInput' => ['nullable', 'regex:/^\s*\d+(?:\s*,\s*\d+)*\s*$|^$/'],
            'live.enabled' => 'required|boolean',
            'live.syncServerIcon' => 'required|boolean',
            'live.runningDescription' => 'required|string|max:5000',
            'motd.sync_panel_logo' => 'nullable|boolean',
            'motd_icon' => 'nullable|file|mimetypes:image/png,image/jpeg,image/gif,image/webp|max:4096',
        ];
    }
}
