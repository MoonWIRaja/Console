<?php

namespace Pterodactyl\Services\Security;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Pterodactyl\Facades\Activity;

class AuthSecurityService
{
    public function __construct(private SecurityAlertService $alertService)
    {
    }

    public function getIdentifierFromRequest(Request $request): ?string
    {
        foreach (['user', 'email', 'username'] as $key) {
            $value = trim((string) $request->input($key, ''));
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    public function isTrustedRequest(Request $request): bool
    {
        return $this->isTrustedIp($request->ip());
    }

    public function isTrustedIp(?string $ip): bool
    {
        if (empty($ip)) {
            return false;
        }

        $trusted = config('security.trusted_ips', []);
        foreach ($trusted as $entry) {
            if ($this->ipMatchesCidr($ip, (string) $entry)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns lock/challenge state for this request.
     *
     * @return array{score:int,challenge_required:bool,locked:bool,retry_after:int}
     */
    public function evaluate(Request $request, ?string $identifier = null): array
    {
        if ($this->isTrustedRequest($request)) {
            return [
                'score' => 0,
                'challenge_required' => false,
                'locked' => false,
                'retry_after' => 0,
            ];
        }

        $lockInfo = $this->getActiveLock($request);
        if (!is_null($lockInfo)) {
            return [
                'score' => max($lockInfo['score'], $this->getScore($request, $identifier)),
                'challenge_required' => true,
                'locked' => true,
                'retry_after' => $lockInfo['retry_after'],
            ];
        }

        $score = $this->getScore($request, $identifier);

        return [
            'score' => $score,
            'challenge_required' => $score >= $this->challengeThreshold(),
            'locked' => false,
            'retry_after' => 0,
        ];
    }

    public function registerFailure(Request $request, int $points, string $reason, ?string $identifier = null): int
    {
        if ($this->isTrustedRequest($request)) {
            return 0;
        }

        $score = $this->incrementScore($this->getScoreKeys($request, $identifier), $points);

        $this->logSecurityEvent("auth_{$reason}", $request, [
            'points' => $points,
            'score' => $score,
        ]);

        if ($score >= $this->longLockThreshold()) {
            $this->applyLock($request, $this->longLockMinutes(), $score, $reason);
        } elseif ($score >= $this->shortLockThreshold()) {
            $this->applyLock($request, $this->shortLockMinutes(), $score, $reason);
        } elseif ($score >= $this->challengeThreshold()) {
            $this->logSecurityEvent('auth_challenge_required', $request, ['score' => $score]);
        }

        return $score;
    }

    public function registerHoneyportHit(string $ip, int $port): int
    {
        if ($this->isTrustedIp($ip)) {
            return 0;
        }

        $ipHash = sha1($ip);
        $scoreKey = $this->scoreKey("ip:{$ipHash}");
        $honeyKey = "security:auth:honeyport:hits:{$ipHash}";

        $score = $this->incrementScore([$scoreKey], 15);
        $hits = $this->incrementCounter($honeyKey, 1, $this->decaySeconds());

        if ($score >= $this->longLockThreshold()) {
            $this->setLockForIp($ip, $this->longLockMinutes(), $score);
        } elseif ($score >= $this->shortLockThreshold()) {
            $this->setLockForIp($ip, $this->shortLockMinutes(), $score);
        }

        if ($hits >= 3) {
            $this->alertService->send(
                'auth_honeyport_hit',
                'Repeated honeyport probes detected',
                [
                    'ip' => $ip,
                    'port' => $port,
                    'hits' => $hits,
                    'score' => $score,
                ],
            );
        }

        Activity::event('auth_honeyport_hit')
            ->property('ip', $ip)
            ->property('port', $port)
            ->property('hits', $hits)
            ->property('score', $score)
            ->log();

        return $score;
    }

    public function clearRisk(Request $request, ?string $identifier = null): void
    {
        if ($this->isTrustedRequest($request)) {
            return;
        }

        $cache = $this->cache();
        foreach ($this->getScoreKeys($request, $identifier) as $key) {
            $cache->forget($key);
        }

        $lockKey = $this->lockKey($request->ip());
        if (!is_null($lockKey)) {
            $cache->forget($lockKey);
        }
    }

    private function applyLock(Request $request, int $minutes, int $score, string $reason): void
    {
        $ip = $request->ip();
        if (empty($ip)) {
            return;
        }

        $this->setLockForIp($ip, $minutes, $score);

        $this->logSecurityEvent('auth_temp_locked', $request, [
            'score' => $score,
            'minutes' => $minutes,
            'reason' => $reason,
        ]);

        $this->alertService->send(
            'auth_temp_locked',
            'Authentication lock triggered',
            [
                'ip' => $ip,
                'route' => $request->path(),
                'score' => $score,
                'minutes' => $minutes,
                'reason' => $reason,
            ],
        );
    }

    private function setLockForIp(string $ip, int $minutes, int $score): void
    {
        $cache = $this->cache();
        $expiresAt = CarbonImmutable::now()->addMinutes($minutes);
        $cache->put($this->lockKey($ip), [
            'expires_at' => $expiresAt->timestamp,
            'score' => $score,
        ], $expiresAt);

        Activity::event('auth_risk_escalated')
            ->property('ip', $ip)
            ->property('score', $score)
            ->property('lock_minutes', $minutes)
            ->log();
    }

    /**
     * @return array{retry_after:int,score:int}|null
     */
    private function getActiveLock(Request $request): ?array
    {
        $lockKey = $this->lockKey($request->ip());
        if (is_null($lockKey)) {
            return null;
        }

        $lock = $this->cache()->get($lockKey);
        if (!is_array($lock) || empty($lock['expires_at'])) {
            return null;
        }

        $retryAfter = max(0, (int) $lock['expires_at'] - CarbonImmutable::now()->timestamp);
        if ($retryAfter <= 0) {
            $this->cache()->forget($lockKey);

            return null;
        }

        return [
            'retry_after' => $retryAfter,
            'score' => (int) ($lock['score'] ?? 0),
        ];
    }

    private function getScore(Request $request, ?string $identifier = null): int
    {
        $scores = array_map(
            fn (string $key) => (int) $this->cache()->get($key, 0),
            $this->getScoreKeys($request, $identifier),
        );

        return !empty($scores) ? max($scores) : 0;
    }

    /**
     * @return string[]
     */
    private function getScoreKeys(Request $request, ?string $identifier = null): array
    {
        $keys = [];
        $ip = $request->ip();
        if (!empty($ip)) {
            $keys[] = $this->scoreKey('ip:' . sha1($ip));
        }

        $fingerprint = $this->fingerprintFromRequest($request);
        if (!is_null($fingerprint)) {
            $keys[] = $this->scoreKey('fp:' . $fingerprint);
        }

        $route = trim((string) ($request->route()?->getName() ?: $request->path()));
        if ($route !== '') {
            $keys[] = $this->scoreKey('route:' . sha1($route . '|' . ($ip ?? 'na')));
        }

        $normalizedIdentifier = $this->normalizeIdentifier($identifier);
        if (!is_null($normalizedIdentifier)) {
            $keys[] = $this->scoreKey('id:' . sha1($normalizedIdentifier));
        }

        return array_values(array_unique($keys));
    }

    private function incrementScore(array $keys, int $points): int
    {
        $max = 0;
        foreach ($keys as $key) {
            $value = $this->incrementCounter($key, $points, $this->decaySeconds());
            $max = max($max, $value);
        }

        return $max;
    }

    private function incrementCounter(string $key, int $points, int $ttlSeconds): int
    {
        $cache = $this->cache();
        $current = (int) $cache->get($key, 0);
        $next = $current + $points;
        $cache->put($key, $next, now()->addSeconds($ttlSeconds));

        return $next;
    }

    private function logSecurityEvent(string $event, Request $request, array $meta = []): void
    {
        $activity = Activity::event($event)->withRequestMetadata();
        foreach ($meta as $key => $value) {
            $activity->property((string) $key, $value);
        }
        $activity->log();
    }

    private function scoreKey(string $suffix): string
    {
        return "security:auth:score:{$suffix}";
    }

    private function lockKey(?string $ip): ?string
    {
        if (empty($ip)) {
            return null;
        }

        return 'security:auth:lock:' . sha1($ip);
    }

    private function cache(): CacheRepository
    {
        $cacheStore = config('security.cache_store');

        return $cacheStore ? Cache::store($cacheStore) : Cache::store();
    }

    private function normalizeIdentifier(?string $identifier): ?string
    {
        if (is_null($identifier)) {
            return null;
        }

        $value = mb_strtolower(trim($identifier));

        return $value !== '' ? $value : null;
    }

    private function fingerprintFromRequest(Request $request): ?string
    {
        $parts = [
            trim((string) $request->header('user-agent', '')),
            trim((string) $request->header('accept-language', '')),
            trim((string) $request->header('accept', '')),
        ];

        $raw = implode('|', $parts);

        return $raw !== '||' ? sha1($raw) : null;
    }

    private function challengeThreshold(): int
    {
        return max(1, (int) config('security.risk.challenge_threshold', 10));
    }

    private function shortLockThreshold(): int
    {
        return max($this->challengeThreshold(), (int) config('security.risk.lock_short_threshold', 18));
    }

    private function longLockThreshold(): int
    {
        return max($this->shortLockThreshold(), (int) config('security.risk.lock_long_threshold', 30));
    }

    private function shortLockMinutes(): int
    {
        return max(1, (int) config('security.risk.lock_short_minutes', 15));
    }

    private function longLockMinutes(): int
    {
        return max($this->shortLockMinutes(), (int) config('security.risk.lock_long_minutes', 360));
    }

    private function decaySeconds(): int
    {
        return max(60, (int) config('security.risk.decay_seconds', 86400));
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
        if ($ipBinary === false || $subnetBinary === false) {
            return false;
        }

        if (strlen($ipBinary) !== strlen($subnetBinary)) {
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

        return ((ord($ipBinary[$byteCount]) & $maskByte) === (ord($subnetBinary[$byteCount]) & $maskByte));
    }
}
