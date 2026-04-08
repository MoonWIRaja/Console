<?php

namespace Pterodactyl\Console\Commands\Security;

use Illuminate\Console\Command;
use Pterodactyl\Services\Security\Agents\SecurityAgentService;

class MonitorSecurityAgentsCommand extends Command
{
    protected $signature = 'p:security:monitor-agents';

    protected $description = 'Marks stale security agents and records agent silence incidents.';

    public function __construct(private SecurityAgentService $agents)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $count = $this->agents->markStaleAgents();
        $this->info(sprintf('Processed security agent heartbeat state. %d stale agent(s) marked.', $count));

        return self::SUCCESS;
    }
}
