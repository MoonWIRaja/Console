<?php

namespace Pterodactyl\Http\Requests\Api\Client\Servers\Split;

use Pterodactyl\Models\Permission;
use Pterodactyl\Contracts\Http\ClientPermissionsRequest;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class GetSplitOverviewRequest extends ClientApiRequest implements ClientPermissionsRequest
{
    public function permission(): string
    {
        return Permission::ACTION_SPLIT_READ;
    }

    public function rules(): array
    {
        return [];
    }
}
