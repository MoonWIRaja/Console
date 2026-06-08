<?php

namespace Pterodactyl\Console\Commands\Discord;

use Illuminate\Console\Command;
use Pterodactyl\Services\Servers\Discord\DiscordPanelAgentSyncService;

class SyncServerDiscordAgentsCommand extends Command
{
    protected $signature = 'p:discord-agents:sync
        {--server= : Limit sync to a server UUID or short identifier.}
        {--loop : Keep running and sync repeatedly.}
        {--sleep=10 : Seconds to sleep between loop iterations.}
        {--quiet-success : Only print warnings and failed syncs.}';

    protected $description = 'Sync enabled per-server Discord panel agents.';

    public function handle(DiscordPanelAgentSyncService $sync): int
    {
        $sleep = max(3, (int) $this->option('sleep'));

        do {
            $results = $sync->syncEnabled($this->option('server') ?: null);

            foreach ($results as $server => $result) {
                $line = sprintf('[%s] %s', $server, $result['message'] ?? 'Synced.');
                if ($result['ok'] ?? false) {
                    if (!$this->option('quiet-success')) {
                        $this->info($line);
                    }
                } else {
                    $this->warn($line);
                }
            }

            if (!$this->option('loop')) {
                break;
            }

            sleep($sleep);
        } while (true);

        return self::SUCCESS;
    }
}
