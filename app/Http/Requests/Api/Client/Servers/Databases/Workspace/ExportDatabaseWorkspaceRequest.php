<?php

namespace Pterodactyl\Http\Requests\Api\Client\Servers\Databases\Workspace;

use Pterodactyl\Models\Permission;
use Pterodactyl\Contracts\Http\ClientPermissionsRequest;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class ExportDatabaseWorkspaceRequest extends ClientApiRequest implements ClientPermissionsRequest
{
    public function permission(): string
    {
        return Permission::ACTION_DATABASE_READ;
    }

    public function rules(): array
    {
        return [
            'schema_only' => ['sometimes', 'boolean'],
        ];
    }
}
