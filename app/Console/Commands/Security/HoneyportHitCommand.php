<?php

namespace Pterodactyl\Console\Commands\Security;

use Illuminate\Console\Command;
use Pterodactyl\Services\Security\AuthSecurityService;

class HoneyportHitCommand extends Command
{
    protected $signature = 'security:honeyport:hit {ip : Source IP address} {port : Honeyport that was accessed}';

    protected $description = 'Record a honeyport access event and escalate authentication risk for that source IP.';

    public function __construct(private AuthSecurityService $security)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $ip = (string) $this->argument('ip');
        $port = (int) $this->argument('port');

        $score = $this->security->registerHoneyportHit($ip, $port);
        $this->line(sprintf('Honeyport hit recorded for %s:%d (score=%d).', $ip, $port, $score));

        return 0;
    }
}
