<?php

namespace Pterodactyl\Console\Commands\Security;

use Illuminate\Console\Command;
use Pterodactyl\Services\Security\AuthSecurityService;

class HoneyportListenCommand extends Command
{
    protected $signature = 'security:honeyport:listen
                            {--ports=22222,23306,28080 : Comma separated list of TCP ports to bind}
                            {--bind=0.0.0.0 : Bind address}';

    protected $description = 'Run a lightweight honeyport listener and record inbound probes.';

    private bool $running = true;

    public function __construct(private AuthSecurityService $security)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $bind = (string) $this->option('bind');
        $ports = $this->parsePorts((string) $this->option('ports'));
        if (empty($ports)) {
            $this->error('No valid ports provided.');

            return 1;
        }

        $servers = [];
        foreach ($ports as $port) {
            $socket = @stream_socket_server("tcp://{$bind}:{$port}", $errno, $errstr);
            if ($socket === false) {
                $this->warn(sprintf('Unable to bind honeyport %d (%s).', $port, $errstr));
                continue;
            }

            stream_set_blocking($socket, false);
            $servers[(int) $socket] = [
                'port' => $port,
                'socket' => $socket,
            ];
            $this->line(sprintf('Honeyport listening on %s:%d', $bind, $port));
        }

        if (empty($servers)) {
            $this->error('Failed to bind any honeyports.');

            return 1;
        }

        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGTERM, function () {
                $this->running = false;
            });
            pcntl_signal(SIGINT, function () {
                $this->running = false;
            });
        }

        while ($this->running) {
            $read = array_map(fn (array $entry) => $entry['socket'], $servers);
            $write = null;
            $except = null;
            $ready = @stream_select($read, $write, $except, 1);
            if ($ready === false || $ready === 0) {
                continue;
            }

            foreach ($read as $socket) {
                $entry = $servers[(int) $socket] ?? null;
                if (is_null($entry)) {
                    continue;
                }

                $peer = null;
                $connection = @stream_socket_accept($socket, 0, $peer);
                if ($connection === false) {
                    continue;
                }

                $ip = $this->extractIp((string) $peer);
                if (!is_null($ip)) {
                    $score = $this->security->registerHoneyportHit($ip, $entry['port']);
                    $this->line(sprintf('[%s] hit on port %d (score=%d)', $ip, $entry['port'], $score));
                }

                @fwrite($connection, "HTTP/1.1 404 Not Found\r\nConnection: close\r\n\r\n");
                @fclose($connection);
            }
        }

        foreach ($servers as $entry) {
            @fclose($entry['socket']);
        }

        return 0;
    }

    /**
     * @return int[]
     */
    private function parsePorts(string $ports): array
    {
        $output = [];
        foreach (explode(',', $ports) as $port) {
            $value = (int) trim($port);
            if ($value > 0 && $value <= 65535) {
                $output[] = $value;
            }
        }

        return array_values(array_unique($output));
    }

    private function extractIp(string $peer): ?string
    {
        // IPv6 peers are enclosed in square brackets by PHP stream sockets.
        if (preg_match('/^\\[(.*)]:(\\d+)$/', $peer, $matches) === 1) {
            return $matches[1];
        }

        if (preg_match('/^(.*):(\\d+)$/', $peer, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }
}
