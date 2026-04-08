<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Illuminate\Http\JsonResponse;
use Pterodactyl\Models\Server;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Models\Subdomains\ServerSubdomain;
use Pterodactyl\Models\Subdomains\SubdomainRecord;
use Pterodactyl\Services\Subdomains\ServerSubdomainService;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Http\Requests\Api\Client\Servers\Subdomains\GetSubdomainRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Subdomains\StoreSubdomainRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Subdomains\DeleteSubdomainRequest;

class NetworkSubdomainController extends ClientApiController
{
    public function __construct(private ServerSubdomainService $service)
    {
        parent::__construct();
    }

    public function index(GetSubdomainRequest $request, Server $server): array
    {
        return $this->transformResponse($server);
    }

    public function store(StoreSubdomainRequest $request, Server $server): array
    {
        $record = SubdomainRecord::query()->findOrFail($request->integer('record_id'));
        $subdomain = $this->service->create($server, $record, $request->string('hostname')->toString());

        Activity::event('server:subdomain.create')
            ->subject($subdomain)
            ->property('subdomain', $subdomain->full_domain)
            ->log();

        return $this->transformResponse($server);
    }

    public function delete(DeleteSubdomainRequest $request, Server $server, int $subdomainId): JsonResponse
    {
        $subdomain = $server->subdomains()->whereKey($subdomainId)->firstOrFail();

        Activity::event('server:subdomain.delete')
            ->subject($subdomain)
            ->property('subdomain', $subdomain->full_domain)
            ->log();

        $this->service->delete($subdomain);

        return new JsonResponse([], JsonResponse::HTTP_NO_CONTENT);
    }

    private function transformResponse(Server $server): array
    {
        $subdomains = $server->subdomains()
            ->with(['domain:id,name', 'record:id,name,record_type'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (ServerSubdomain $subdomain) => [
                'id' => $subdomain->id,
                'hostname_label' => $subdomain->hostname_label,
                'full_domain' => $subdomain->full_domain,
                'record_type' => $subdomain->record_type,
                'resolved_target' => $subdomain->resolved_target,
                'created_at' => optional($subdomain->created_at)->toAtomString(),
                'domain' => [
                    'id' => $subdomain->domain->id,
                    'name' => $subdomain->domain->name,
                ],
                'record' => [
                    'id' => $subdomain->record->id,
                    'name' => $subdomain->record->name,
                    'record_type' => $subdomain->record->record_type,
                ],
            ])
            ->values();

        $templates = SubdomainRecord::query()
            ->with(['domain:id,name'])
            ->whereHas('nests', fn ($query) => $query->where('nests.id', $server->nest_id))
            ->orderBy('name')
            ->get()
            ->map(fn (SubdomainRecord $record) => [
                'id' => $record->id,
                'name' => $record->name,
                'record_type' => $record->record_type,
                'domain' => [
                    'id' => $record->domain->id,
                    'name' => $record->domain->name,
                ],
            ])
            ->values();

        return [
            'items' => $subdomains,
            'templates' => $templates,
        ];
    }
}
