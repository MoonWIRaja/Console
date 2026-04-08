<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Database;
use Pterodactyl\Models\Permission;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Http\Requests\Api\Client\Servers\Databases\Workspace\GetDatabaseWorkspaceHealthRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Databases\Workspace\GetDatabaseWorkspaceRowsRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Databases\Workspace\GetDatabaseWorkspaceTablesRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Databases\Workspace\ImportDatabaseWorkspaceRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Databases\Workspace\ExecuteDatabaseWorkspaceQueryRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Databases\Workspace\ExportDatabaseWorkspaceRequest;
use Pterodactyl\Services\Databases\DatabaseWorkspaceService;

class DatabaseWorkspaceController extends ClientApiController
{
    public function __construct(private DatabaseWorkspaceService $workspaceService)
    {
        parent::__construct();
    }

    public function tables(GetDatabaseWorkspaceTablesRequest $request, Server $server, Database $database): array
    {
        return ['data' => $this->workspaceService->getTables($database)];
    }

    public function rows(GetDatabaseWorkspaceRowsRequest $request, Server $server, Database $database): array
    {
        return ['data' => $this->workspaceService->getTableRows(
            $database,
            (string) $request->validated('table'),
            (int) $request->validated('page', 1),
            (int) $request->validated('per_page', 25),
        )];
    }

    public function health(GetDatabaseWorkspaceHealthRequest $request, Server $server, Database $database): array
    {
        return ['data' => $this->workspaceService->getHealth($database)];
    }

    public function query(ExecuteDatabaseWorkspaceQueryRequest $request, Server $server, Database $database): array
    {
        return ['data' => $this->workspaceService->executeQuery(
            $database,
            (string) $request->validated('query'),
            $request->user()->can(Permission::ACTION_DATABASE_UPDATE, $server)
        )];
    }

    public function import(ImportDatabaseWorkspaceRequest $request, Server $server, Database $database): array
    {
        $sql = '';
        $file = $request->file('sql_file');
        if ($file) {
            $sql = (string) file_get_contents($file->getRealPath());
        } else {
            $sql = (string) $request->validated('sql');
        }

        return ['data' => $this->workspaceService->import($database, $sql)];
    }

    public function export(ExportDatabaseWorkspaceRequest $request, Server $server, Database $database): Response
    {
        $export = $this->workspaceService->export(
            $database,
            filter_var($request->query('schema_only', false), FILTER_VALIDATE_BOOL)
        );

        return response($export['contents'], Response::HTTP_OK, [
            'Content-Type' => 'application/sql; charset=utf-8',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $export['filename']),
            'X-Database-Export-Time' => (string) $export['execution_time_ms'],
        ]);
    }
}
