<?php

namespace Pterodactyl\Http\Requests\Api\Client\Servers\Databases\Workspace;

use Pterodactyl\Models\Permission;
use Pterodactyl\Contracts\Http\ClientPermissionsRequest;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class ImportDatabaseWorkspaceRequest extends ClientApiRequest implements ClientPermissionsRequest
{
    public function permission(): string
    {
        return Permission::ACTION_DATABASE_UPDATE;
    }

    public function rules(): array
    {
        return [
            'sql' => ['nullable', 'string', 'required_without:sql_file', 'max:1000000'],
            'sql_file' => [
                'nullable',
                'file',
                'required_without:sql',
                'max:10240',
                'mimes:sql,txt',
                'mimetypes:text/plain,text/x-sql,application/sql,application/octet-stream',
            ],
        ];
    }
}
