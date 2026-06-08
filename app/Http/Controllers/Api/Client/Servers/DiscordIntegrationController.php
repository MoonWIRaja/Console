<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Illuminate\Http\JsonResponse;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Models\Server;
use Pterodactyl\Services\Servers\Discord\DiscordPanelAgentSyncService;
use Pterodactyl\Services\Servers\Discord\ServerDiscordIntegrationService;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Http\Requests\Api\Client\Servers\Discord\GetDiscordIntegrationRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Discord\InstallDiscordAgentRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Discord\UpdateDiscordIntegrationRequest;

class DiscordIntegrationController extends ClientApiController
{
    public function __construct(
        private ServerDiscordIntegrationService $service,
        private DiscordPanelAgentSyncService $syncService,
    )
    {
        parent::__construct();
    }

    public function index(GetDiscordIntegrationRequest $request, Server $server): JsonResponse
    {
        return new JsonResponse($this->service->status($server));
    }

    public function update(UpdateDiscordIntegrationRequest $request, Server $server): JsonResponse
    {
        $response = $this->service->update($server, $request->validated());

        Activity::event('server:discord.update')
            ->subject($server)
            ->property('enabled', (bool) data_get($response, 'integration.enabled'))
            ->property('agent_adapter', data_get($response, 'agent.adapter'))
            ->log();

        return new JsonResponse($response);
    }

    public function install(InstallDiscordAgentRequest $request, Server $server): JsonResponse
    {
        $response = $this->service->install($server);

        Activity::event('server:discord.agent.install')
            ->subject($server)
            ->property('agent_adapter', data_get($response, 'agent.adapter'))
            ->property('detected_game_type', data_get($response, 'agent.detected_game_type'))
            ->log();

        return new JsonResponse($response);
    }

    public function sync(InstallDiscordAgentRequest $request, Server $server): JsonResponse
    {
        $this->syncService->syncServer($server);
        $response = $this->service->status($server);

        Activity::event('server:discord.agent.sync')
            ->subject($server)
            ->property('connection_status', data_get($response, 'agent.connection_status'))
            ->log();

        return new JsonResponse($response);
    }

    public function reset(InstallDiscordAgentRequest $request, Server $server): JsonResponse
    {
        $response = $this->service->reset($server);

        Activity::event('server:discord.agent.reset')
            ->subject($server)
            ->log();

        return new JsonResponse($response);
    }
}
