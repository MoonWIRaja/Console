<?php

namespace Pterodactyl\Console\Commands\DownDetector;

use Illuminate\Console\Command;
use Pterodactyl\Services\DownDetector\DownDetectorRunnerService;

class RunDownDetectorCommand extends Command
{
    protected $signature = 'p:down-detector:run {--force : Ignore the configured interval throttle for this execution}';

    protected $description = 'Run the native down detector checks for nodes and servers.';

    public function __construct(private DownDetectorRunnerService $runner)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $result = $this->runner->run((bool) $this->option('force'));

        if (($result['skipped'] ?? false) === true) {
            $this->info(sprintf('Down detector skipped: %s', $result['reason'] ?? 'unknown'));

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Down detector completed. Nodes checked: %d, servers checked: %d.',
            (int) data_get($result, 'nodes.checked', 0),
            (int) data_get($result, 'servers.checked', 0)
        ));

        return self::SUCCESS;
    }
}
