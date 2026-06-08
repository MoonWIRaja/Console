<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Illuminate\Http\Response;
use Pterodactyl\Models\Server;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Repositories\Wings\DaemonPowerRepository;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Http\Requests\Api\Client\Servers\SendPowerRequest;
use Pterodactyl\Services\Servers\Discord\DiscordPanelAgentSyncService;
use Pterodactyl\Services\DownDetector\DownDetectorAutoRestartService;

class PowerController extends ClientApiController
{
    /**
     * PowerController constructor.
     */
    public function __construct(
        private DaemonPowerRepository $repository,
        private DownDetectorAutoRestartService $autoRestart,
        private DiscordPanelAgentSyncService $discordAgentSync,
    )
    {
        parent::__construct();
    }

    /**
     * Send a power action to a server.
     */
    public function index(SendPowerRequest $request, Server $server): Response
    {
        $this->repository->setServer($server)->send(
            $request->input('signal')
        );

        $this->autoRestart->recordManualIntent($server, (string) $request->input('signal'));

        Activity::event(strtolower("server:power.{$request->input('signal')}"))
            ->subject($server)
            ->log();

        $this->discordAgentSync->announcePowerEvent($server, (string) $request->input('signal'));

        return $this->returnNoContent();
    }
}
