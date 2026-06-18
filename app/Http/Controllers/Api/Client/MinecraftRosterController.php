<?php

namespace Pterodactyl\Http\Controllers\Api\Client;

use Illuminate\Http\JsonResponse;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;
use Pterodactyl\Services\Minecraft\MinecraftRosterService;

class MinecraftRosterController extends ClientApiController
{
    public function __construct(private MinecraftRosterService $roster)
    {
        parent::__construct();
    }

    /**
     * Returns the unique, de-duplicated roster of premium (online-mode=true) Minecraft
     * players across every server on the panel. Used to populate the live-console
     * "photobook" background. Heavily cached by the service.
     */
    public function __invoke(ClientApiRequest $request): JsonResponse
    {
        $fresh = $request->boolean('fresh');
        $players = $this->roster->get($fresh);
        $online = array_flip($this->roster->onlineNames());

        $data = array_map(static fn (array $p): array => [
            'name' => $p['name'],
            'uuid' => $p['uuid'],
            'online' => isset($online[strtolower($p['name'])]),
        ], $players);

        return new JsonResponse([
            'data' => $data,
            'meta' => ['count' => count($data), 'online' => count($online)],
        ]);
    }
}
