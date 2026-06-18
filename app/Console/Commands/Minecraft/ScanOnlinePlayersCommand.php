<?php

namespace Pterodactyl\Console\Commands\Minecraft;

use Illuminate\Console\Command;
use Pterodactyl\Services\Minecraft\MinecraftRosterService;

class ScanOnlinePlayersCommand extends Command
{
    protected $signature = 'p:minecraft:scan-online';

    protected $description = 'Refresh the cached set of currently-online Minecraft players used by the live-console background glow.';

    public function __construct(private MinecraftRosterService $roster)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $online = $this->roster->refreshOnline();

        $this->info(sprintf('Minecraft online scan complete: %d player(s) online.', count($online)));

        return self::SUCCESS;
    }
}
