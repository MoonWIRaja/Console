<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Illuminate\Http\JsonResponse;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Models\Server;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Pterodactyl\Services\Servers\Players\PlayerDirectoryService;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Http\Requests\Api\Client\Servers\Players\ListPlayersRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Players\GetPlayerDetailsRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Players\RunPlayerActionRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Players\GetPlayersCapabilitiesRequest;

class PlayerController extends ClientApiController
{
    public function __construct(private PlayerDirectoryService $service)
    {
        parent::__construct();
    }

    public function index(ListPlayersRequest $request, Server $server): JsonResponse
    {
        return new JsonResponse($this->service->list(
            $server,
            $request->query('scope'),
            $request->query('search')
        ));
    }

    public function capabilities(GetPlayersCapabilitiesRequest $request, Server $server): JsonResponse
    {
        return new JsonResponse($this->service->capabilities($server));
    }

    public function show(GetPlayerDetailsRequest $request, Server $server, string $player): JsonResponse
    {
        $response = $this->service->profile($server, $player);
        if (!$response) {
            throw new NotFoundHttpException('Player record was not found.');
        }

        return new JsonResponse($response);
    }

    public function inventory(GetPlayerDetailsRequest $request, Server $server, string $player): JsonResponse
    {
        $response = $this->service->inventory($server, $player);
        if (!$response) {
            throw new NotFoundHttpException('Player record was not found.');
        }

        return new JsonResponse($response);
    }

    public function statistics(GetPlayerDetailsRequest $request, Server $server, string $player): JsonResponse
    {
        $response = $this->service->statistics($server, $player);
        if (!$response) {
            throw new NotFoundHttpException('Player record was not found.');
        }

        return new JsonResponse($response);
    }

    public function action(RunPlayerActionRequest $request, Server $server, string $player): JsonResponse
    {
        $response = $this->service->action(
            $server,
            $player,
            (string) $request->input('action'),
            (array) $request->input('context', [])
        );

        if (!$response) {
            throw new NotFoundHttpException('Player record was not found.');
        }

        if (!(bool) ($response['accepted'] ?? false)) {
            throw new BadRequestHttpException((string) ($response['message'] ?? 'Unable to run action.'));
        }

        Activity::event('server:players.action')
            ->subject($server)
            ->property('player_id', $player)
            ->property('action', (string) $request->input('action'))
            ->log();

        return new JsonResponse($response);
    }
}
