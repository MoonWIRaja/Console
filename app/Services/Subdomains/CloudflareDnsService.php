<?php

namespace Pterodactyl\Services\Subdomains;

use RuntimeException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Pterodactyl\Models\Subdomains\SubdomainDomain;

class CloudflareDnsService
{
    private const BASE_URL = 'https://api.cloudflare.com/client/v4';

    public function createAddressRecord(
        SubdomainDomain $domain,
        string $hostname,
        string $target,
        ?int $ttl = null,
        bool $proxied = false,
    ): string {
        $type = filter_var($target, FILTER_VALIDATE_IP) ? 'A' : 'CNAME';

        $payload = [
            'type' => $type,
            'name' => $hostname,
            'content' => $target,
            'ttl' => $ttl ?? 1,
            'proxied' => $type === 'CNAME' ? $proxied : false,
        ];

        return $this->send($domain, 'POST', '/dns_records', $payload);
    }

    public function createSrvRecord(
        SubdomainDomain $domain,
        string $hostname,
        string $service,
        string $protocol,
        int $priority,
        int $weight,
        int $port,
        string $target,
        ?int $ttl = null,
    ): string {
        $payload = [
            'type' => 'SRV',
            'name' => sprintf('%s.%s.%s', $this->normalizeService($service), $this->normalizeService($protocol), $hostname),
            'ttl' => $ttl ?? 1,
            'data' => [
                'port' => $port,
                'priority' => $priority,
                'target' => $target,
                'weight' => $weight,
            ],
        ];

        return $this->send($domain, 'POST', '/dns_records', $payload);
    }

    public function deleteRecord(SubdomainDomain $domain, string $recordId): void
    {
        $response = $this->request($domain, 'DELETE', sprintf('/dns_records/%s', $recordId));

        if ($response->successful()) {
            return;
        }

        $errors = collect($response->json('errors', []))->pluck('message')->filter()->implode(' ');
        if ($response->status() === 404 || str_contains(strtolower($errors), 'not found')) {
            return;
        }

        throw new RuntimeException($errors !== '' ? $errors : 'Cloudflare rejected the DNS record deletion request.');
    }

    private function send(SubdomainDomain $domain, string $method, string $path, array $payload): string
    {
        $response = $this->request($domain, $method, $path, $payload);

        if (!$response->successful() || !$response->json('success')) {
            $errors = collect($response->json('errors', []))->pluck('message')->filter()->implode(' ');

            throw new RuntimeException($errors !== '' ? $errors : 'Cloudflare rejected the DNS record request.');
        }

        $recordId = Arr::get($response->json(), 'result.id');
        if (!is_string($recordId) || $recordId === '') {
            throw new RuntimeException('Cloudflare did not return a DNS record identifier.');
        }

        return $recordId;
    }

    private function request(SubdomainDomain $domain, string $method, string $path, array $payload = [])
    {
        return Http::withToken($domain->getDecryptedApiToken())
            ->acceptJson()
            ->contentType('application/json')
            ->send(
                $method,
                sprintf('%s/zones/%s%s', self::BASE_URL, $domain->zone_identifier, $path),
                $payload === [] ? [] : ['json' => $payload]
            );
    }

    private function normalizeService(string $value): string
    {
        $trimmed = trim($value);

        return str_starts_with($trimmed, '_') ? $trimmed : '_' . $trimmed;
    }
}
