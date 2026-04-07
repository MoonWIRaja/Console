<?php

namespace Pterodactyl\Services\Subdomains;

use RuntimeException;
use Illuminate\Support\Facades\DB;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Subdomains\ServerSubdomain;
use Pterodactyl\Models\Subdomains\SubdomainRecord;

class ServerSubdomainService
{
    public function __construct(private CloudflareDnsService $cloudflare)
    {
    }

    public function create(Server $server, SubdomainRecord $record, string $hostnameLabel): ServerSubdomain
    {
        $record->loadMissing('domain');

        if (!$record->nests()->whereKey($server->nest_id)->exists()) {
            throw new RuntimeException('This subdomain template is not available for the current server nest.');
        }

        $hostname = strtolower(trim($hostnameLabel));
        $fullDomain = sprintf('%s.%s', $hostname, $record->domain->name);

        if (ServerSubdomain::query()->where('domain_id', $record->domain_id)->where('hostname_label', $hostname)->exists()) {
            throw new RuntimeException('This subdomain already exists for the selected domain.');
        }

        $routeTarget = $this->resolveRouteTarget($server);
        $providerRecordIds = [];

        try {
            $providerRecordIds[] = $this->cloudflare->createAddressRecord(
                $record->domain,
                $fullDomain,
                $routeTarget,
                $record->ttl,
                $record->proxied
            );

            if ($record->record_type === SubdomainRecord::TYPE_SRV) {
                $srvTarget = filter_var($routeTarget, FILTER_VALIDATE_IP) ? $fullDomain : $routeTarget;

                $providerRecordIds[] = $this->cloudflare->createSrvRecord(
                    $record->domain,
                    $fullDomain,
                    $record->service ?: '_minecraft',
                    $record->protocol ?: '_tcp',
                    $record->priority ?? 0,
                    $record->weight ?? 0,
                    $server->allocation->port,
                    $srvTarget,
                    $record->ttl
                );
            }
        } catch (\Throwable $exception) {
            foreach ($providerRecordIds as $recordId) {
                try {
                    $this->cloudflare->deleteRecord($record->domain, $recordId);
                } catch (\Throwable) {
                    // If cleanup fails, preserve the original provisioning error.
                }
            }

            throw $exception;
        }

        return DB::transaction(function () use ($server, $record, $hostname, $fullDomain, $routeTarget, $providerRecordIds) {
            return ServerSubdomain::query()->create([
                'server_id' => $server->id,
                'domain_id' => $record->domain_id,
                'subdomain_record_id' => $record->id,
                'hostname_label' => $hostname,
                'full_domain' => $fullDomain,
                'record_type' => $record->record_type,
                'resolved_target' => $routeTarget,
                'provider_record_ids' => $providerRecordIds,
            ]);
        });
    }

    public function delete(ServerSubdomain $subdomain): void
    {
        $subdomain->loadMissing('domain');

        foreach ($subdomain->provider_record_ids ?? [] as $recordId) {
            if (!is_string($recordId) || $recordId === '') {
                continue;
            }

            $this->cloudflare->deleteRecord($subdomain->domain, $recordId);
        }

        $subdomain->delete();
    }

    public function resolveRouteTarget(Server $server): string
    {
        $allocationAlias = trim((string) ($server->allocation->alias ?? ''));
        if ($this->isUsableDnsTarget($allocationAlias)) {
            return $allocationAlias;
        }

        $nodeFqdn = trim((string) $server->node->fqdn);
        if ($this->isUsableDnsTarget($nodeFqdn)) {
            return $nodeFqdn;
        }

        throw new RuntimeException(
            'The node FQDN or allocation hostname is not configured for DNS routing. Configure a valid hostname on the node or primary allocation first.'
        );
    }

    private function isUsableDnsTarget(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        if (filter_var($value, FILTER_VALIDATE_IP)) {
            return true;
        }

        return (bool) preg_match('/^(?=.{1,253}$)(?!-)(?:[a-z0-9-]{1,63}\.)+[a-z]{2,63}$/i', $value);
    }
}
