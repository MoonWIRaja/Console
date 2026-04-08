<?php

namespace Pterodactyl\Http\Requests\Api\Client\Servers\Split;

use Pterodactyl\Models\Permission;
use Pterodactyl\Contracts\Http\ClientPermissionsRequest;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class DeleteSplitServerRequest extends ClientApiRequest implements ClientPermissionsRequest
{
    public function permission(): string
    {
        return Permission::ACTION_SPLIT_DELETE;
    }

    public function rules(): array
    {
        return [
            'confirm' => 'required|string',
        ];
    }
}
