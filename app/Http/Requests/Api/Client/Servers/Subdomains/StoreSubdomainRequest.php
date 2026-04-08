<?php

namespace Pterodactyl\Http\Requests\Api\Client\Servers\Subdomains;

use Pterodactyl\Models\Permission;
use Pterodactyl\Contracts\Http\ClientPermissionsRequest;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class StoreSubdomainRequest extends ClientApiRequest implements ClientPermissionsRequest
{
    public function permission(): string
    {
        return Permission::ACTION_SUBDOMAIN_CREATE;
    }

    public function rules(): array
    {
        return [
            'record_id' => 'required|integer|exists:subdomain_records,id',
            'hostname' => [
                'required',
                'string',
                'min:3',
                'max:63',
                'regex:/^(?!-)[a-z0-9-]{3,63}(?<!-)$/',
            ],
        ];
    }
}
