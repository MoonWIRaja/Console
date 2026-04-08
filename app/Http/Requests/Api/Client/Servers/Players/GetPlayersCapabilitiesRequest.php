<?php

namespace Pterodactyl\Http\Requests\Api\Client\Servers\Players;

use Pterodactyl\Models\Permission;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class GetPlayersCapabilitiesRequest extends ClientApiRequest
{
    public function permission(): string
    {
        return Permission::ACTION_CONTROL_CONSOLE;
    }
}
