<?php

namespace Pterodactyl\Services\Databases;

use Illuminate\Config\Repository as ConfigRepository;
use Pterodactyl\Models\DatabaseHost;

class InternalHostResolver
{
    public function __construct(private ConfigRepository $config)
    {
    }

    public function forDatabaseHost(DatabaseHost $databaseHost): string
    {
        return $this->forHost($databaseHost->host);
    }

    public function forHost(string $host): string
    {
        $normalized = strtolower(trim($host));
        $overrides = $this->config->get('pterodactyl.databases.internal_host_overrides', []);

        return $overrides[$normalized] ?? $host;
    }
}
