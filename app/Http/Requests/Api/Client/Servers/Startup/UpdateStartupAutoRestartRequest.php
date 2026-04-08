<?php

namespace Pterodactyl\Http\Requests\Api\Client\Servers\Startup;

use Pterodactyl\Models\Permission;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class UpdateStartupAutoRestartRequest extends ClientApiRequest
{
    public function permission(): string
    {
        return Permission::ACTION_STARTUP_UPDATE;
    }

    public function rules(): array
    {
        return [
            'enabled' => 'required|boolean',
        ];
    }
}
