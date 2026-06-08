<?php

namespace Pterodactyl\Http\Requests\Api\Client\Servers\Discord;

use Pterodactyl\Models\Permission;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class GetDiscordIntegrationRequest extends ClientApiRequest
{
    public function permission(): string
    {
        return Permission::ACTION_DISCORD_READ;
    }
}
