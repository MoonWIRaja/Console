<?php

namespace Pterodactyl\Http\Requests\Api\Client\Servers\Split;

use Pterodactyl\Models\Permission;
use Pterodactyl\Contracts\Http\ClientPermissionsRequest;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class StoreSplitServerRequest extends ClientApiRequest implements ClientPermissionsRequest
{
    public function permission(): string
    {
        return Permission::ACTION_SPLIT_CREATE;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|min:1|max:191',
            'cpu' => 'required|integer|min:1',
            'memory' => 'required|integer|min:512',
            'disk' => 'required|integer|min:1',
            'swap' => 'required|integer|min:0',
            'copy_subusers' => 'sometimes|boolean',
        ];
    }
}
