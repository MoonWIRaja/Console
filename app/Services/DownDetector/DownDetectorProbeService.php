<?php

namespace Pterodactyl\Services\DownDetector;

use Illuminate\Support\Facades\Http;
use Pterodactyl\Models\Node;
use Pterodactyl\Models\Server;
use RuntimeException;

class DownDetectorProbeService
{
    public function probeNode(Node $node, int $timeoutMs): array
    {
        $response = $this->wingsRequest($node, $timeoutMs)
            ->get('/api/system');

        if (!$response->successful()) {
            throw new RuntimeException(sprintf('Wings returned HTTP %s for /api/system.', $response->status()));
        }

        $payload = $response->json();

        return is_array($payload) ? $payload : [];
    }

    public function probeServer(Server $server, int $timeoutMs): array
    {
        $response = $this->wingsRequest($server->node, $timeoutMs)
            ->get(sprintf('/api/servers/%s', $server->uuid));

        if (!$response->successful()) {
            throw new RuntimeException(sprintf('Wings returned HTTP %s for server details.', $response->status()));
        }

        $payload = $response->json();

        return is_array($payload) ? $payload : [];
    }

    public function probeTcpPort(string $host, int $port, int $timeoutMs): void
    {
        $targetHost = str_contains($host, ':') && !filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
            ? '[' . trim($host, '[]') . ']'
            : $host;

        $socket = @stream_socket_client(
            sprintf('tcp://%s:%d', $targetHost, $port),
            $errorNumber,
            $errorString,
            max($timeoutMs / 1000, 1),
            STREAM_CLIENT_CONNECT
        );

        if (!is_resource($socket)) {
            throw new RuntimeException(sprintf(
                'TCP probe failed for %s:%d (%s%s).',
                $host,
                $port,
                $errorNumber ?: 'socket',
                $errorString ? ': ' . $errorString : ''
            ));
        }

        fclose($socket);
    }

    private function wingsRequest(Node $node, int $timeoutMs)
    {
        return Http::acceptJson()
            ->withToken($node->getDecryptedKey())
            ->baseUrl(rtrim($node->getConnectionAddress(), '/'))
            ->timeout(max($timeoutMs / 1000, 1))
            ->connectTimeout(max($timeoutMs / 1000, 1))
            ->withOptions([
                'verify' => app()->environment('production'),
            ]);
    }
}
