<?php

namespace Pterodactyl\Http\Requests\Api\Client\Servers\Subdomains;

use Pterodactyl\Models\Permission;
use Pterodactyl\Contracts\Http\ClientPermissionsRequest;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class DeleteSubdomainRequest extends ClientApiRequest implements ClientPermissionsRequest
{
    public function permission(): string
    {
        return Permission::ACTION_SUBDOMAIN_DELETE;
    }

    public function rules(): array
    {
        return [];
    }
}
