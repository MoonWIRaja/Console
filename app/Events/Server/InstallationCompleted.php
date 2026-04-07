<?php

namespace Pterodactyl\Events\Server;

use Pterodactyl\Events\Event;
use Pterodactyl\Models\Server;
use Illuminate\Queue\SerializesModels;

class InstallationCompleted extends Event
{
    use SerializesModels;

    public function __construct(public Server $server, public bool $isInitialInstall)
    {
    }
}
