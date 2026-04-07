<?php

namespace Pterodactyl\Services\Security;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SecurityRuntimePolicyService
{
    public function __construct(private SecurityCenterSettingsService $settings)
    {
    }

    public function denyIp(string $ip, ?int $minutes = null, array $meta = []): void
    {
        $this->cache()->put(
            $this->ipKey($ip),
            $this->payload($meta, $minutes ?? $this->settings->runtimeIpDenyMinutes()),
            now()->addMinutes($minutes ?? $this->settings->runtimeIpDenyMinutes())
        );
    }

    public function denyFingerprint(string $fingerprint, ?int $minutes = null, array $meta = []): void
    {
        $this->cache()->put(
            $this->fingerprintKey($fingerprint),
            $this->payload($meta, $minutes ?? $this->settings->runtimeFingerprintDenyMinutes()),
            now()->addMinutes($minutes ?? $this->settings->runtimeFingerprintDenyMinutes())
        );
    }

    public function holdRoute(string $route, ?int $minutes = null, array $meta = []): void
    {
        $route = trim($route);
        if ($route === '') {
            return;
        }

        $this->cache()->put(
            $this->routeKey($route),
            $this->payload($meta, $minutes ?? $this->settings->runtimeRouteHoldMinutes()),
            now()->addMinutes($minutes ?? $this->settings->runtimeRouteHoldMinutes())
        );
    }

    public function fingerprintFromRequest(Request $request): ?string
    {
        $parts = [
            trim((string) $request->header('user-agent', '')),
            trim((string) $request->header('accept-language', '')),
            trim((string) $request->header('accept', '')),
        ];

        $raw = implode('|', $parts);

        return $raw !== '||' ? sha1($raw) : null;
    }

    public function breakGlassReason(Request $request): ?string
    {
        $ip = $request->ip();
        if ($ip && $this->matchesAnyNetwork($ip, $this->settings->breakGlassTrustedNetworks())) {
            return 'trusted_network';
        }

        $configured = $this->settings->emergencyToken();
        $provided = trim((string) $request->header('X-Security-Recovery-Token', ''));
        if ($configured && $provided !== '' && hash_equals($configured, $provided)) {
            return 'emergency_token';
        }

        return null;
    }

    public function match(Request $request): ?array
    {
        $ip = trim((string) $request->ip());
        if ($ip !== '' && ($payload = $this->cache()->get($this->ipKey($ip)))) {
            return [
                'reason' => 'source_ip_denied',
                'scope' => 'ip',
                'payload' => $payload,
                'message' => 'Request denied by active security IP policy.',
            ];
        }

        $fingerprint = $this->fingerprintFromRequest($request);
        if ($fingerprint && ($payload = $this->cache()->get($this->fingerprintKey($fingerprint)))) {
            return [
                'reason' => 'fingerprint_denied',
                'scope' => 'fingerprint',
                'payload' => $payload,
                'message' => 'Request denied by active browser fingerprint policy.',
            ];
        }

        $route = trim((string) ($request->route()?->getName() ?: $request->path()));
        if ($route !== '' && ($payload = $this->cache()->get($this->routeKey($route)))) {
            return [
                'reason' => 'route_temporarily_held',
                'scope' => 'route',
                'payload' => $payload,
                'message' => 'This route is temporarily held by Security Center.',
            ];
        }

        return null;
    }

    public function shouldLogPolicyEvent(string $reason, string $scopeKey): bool
    {
        return $this->cache()->add(
            sprintf('security:center:policy-log:%s:%s', sha1($reason), sha1($scopeKey)),
            true,
            now()->addSeconds(60)
        );
    }

    public function shouldLogBreakGlass(Request $request, string $reason): bool
    {
        $key = sprintf('security:center:break-glass:%s:%s', $reason, sha1((string) $request->ip() . '|' . (string) $request->path()));

        return $this->cache()->add($key, true, now()->addSeconds(60));
    }

    public function matchesTrustedNetwork(?string $ip): bool
    {
        return $ip ? $this->matchesAnyNetwork($ip, $this->settings->trustedNetworks()) : false;
    }

    private function payload(array $meta, int $minutes): array
    {
        return $meta + [
            'expires_at' => now()->addMinutes($minutes)->toIso8601String(),
        ];
    }

    private function ipKey(string $ip): string
    {
        return 'security:center:deny-ip:' . sha1($ip);
    }

    private function fingerprintKey(string $fingerprint): string
    {
        return 'security:center:deny-fingerprint:' . $fingerprint;
    }

    private function routeKey(string $route): string
    {
        return 'security:center:hold-route:' . sha1($route);
    }

    private function cache(): CacheRepository
    {
        $cacheStore = config('security.cache_store');

        return $cacheStore ? Cache::store($cacheStore) : Cache::store();
    }

    private function matchesAnyNetwork(string $ip, array $entries): bool
    {
        foreach ($entries as $entry) {
            if ($this->ipMatchesCidr($ip, (string) $entry)) {
                return true;
            }
        }

        return false;
    }

    private function ipMatchesCidr(string $ip, string $entry): bool
    {
        $entry = trim($entry);
        if ($entry === '') {
            return false;
        }

        if (!str_contains($entry, '/')) {
            return hash_equals($entry, $ip);
        }

        [$subnet, $maskBits] = explode('/', $entry, 2);
        $ipBinary = @inet_pton($ip);
        $subnetBinary = @inet_pton($subnet);
        if ($ipBinary === false || $subnetBinary === false || strlen($ipBinary) !== strlen($subnetBinary)) {
            return false;
        }

        $mask = (int) $maskBits;
        $byteCount = intdiv($mask, 8);
        $bitRemainder = $mask % 8;

        if ($byteCount > 0 && substr($ipBinary, 0, $byteCount) !== substr($subnetBinary, 0, $byteCount)) {
            return false;
        }

        if ($bitRemainder === 0) {
            return true;
        }

        $maskByte = (~(0xFF >> $bitRemainder)) & 0xFF;

        return (ord($ipBinary[$byteCount]) & $maskByte) === (ord($subnetBinary[$byteCount]) & $maskByte);
    }
}
