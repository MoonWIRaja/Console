<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Illuminate\Http\JsonResponse;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Permission;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Services\Servers\SplitServerService;
use Pterodactyl\Http\Requests\Api\Client\Servers\Split\GetSplitOverviewRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Split\StoreSplitServerRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Split\UpdateSplitServerRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Split\DeleteSplitServerRequest;

class SplitController extends ClientApiController
{
    public function __construct(private SplitServerService $splitServerService)
    {
        parent::__construct();
    }

    public function index(GetSplitOverviewRequest $request, Server $server): array
    {
        return [
            'data' => $this->splitServerService->overview($server),
        ];
    }

    public function store(StoreSplitServerRequest $request, Server $server): JsonResponse
    {
        $copySubusers = $request->boolean('copy_subusers');
        if (
            $copySubusers
            && (!$request->user()->can(Permission::ACTION_USER_READ, $server)
                || !$request->user()->can(Permission::ACTION_USER_CREATE, $server))
        ) {
            abort(403, 'You do not have permission to copy subusers to a split server.');
        }

        $child = $this->splitServerService->create($server, $request->validated(), $copySubusers);

        return new JsonResponse([
            'data' => [
                'id' => $child->id,
                'uuid' => $child->uuid,
                'identifier' => $child->identifier,
                'name' => $child->name,
            ],
        ], 201);
    }

    public function update(UpdateSplitServerRequest $request, Server $server, int $split_id): JsonResponse
    {
        $split = Server::query()->find($split_id);
        if (!$split) {
            throw new NotFoundHttpException('The requested resource could not be found on the server.');
        }

        $this->splitServerService->update($server, $split, $request->validated());

        return new JsonResponse([], 204);
    }

    public function delete(DeleteSplitServerRequest $request, Server $server, int $split_id): JsonResponse
    {
        $split = Server::query()->find($split_id);
        if (!$split) {
            throw new NotFoundHttpException('The requested resource could not be found on the server.');
        }

        $confirm = $request->validated('confirm');
        if (!is_string($confirm)) {
            $confirm = (string) $request->input('confirm', '');
        }

        $this->splitServerService->delete($server, $split, $confirm);

        return new JsonResponse([], 204);
    }
}
