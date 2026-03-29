<?php

namespace Pterodactyl\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class SetSecurityHeaders
{
    private const BASE_HEADERS = [
        'X-Content-Type-Options' => 'nosniff',
        'X-XSS-Protection' => '1; mode=block',
        'Referrer-Policy' => 'no-referrer-when-downgrade',
    ];

    /**
     * Enforces some basic security headers on all responses returned by the software.
     * If a header has already been set in another location within the code it will be
     * skipped over here.
     *
     * @param (\Closure(mixed): \Illuminate\Http\Response) $next
     */
    public function handle(Request $request, \Closure $next): mixed
    {
        try {
            $response = $next($request);
        } catch (\Throwable $exception) {
            if ($this->isFiuuReturnRequest($request)) {
                Log::error('Unhandled exception while rendering the Fiuu return route.', [
                    'path' => $request->path(),
                    'method' => $request->method(),
                    'reference' => $request->input('RefNo') ?? $request->input('reference') ?? $request->input('orderid'),
                    'exception' => $exception,
                ]);

                return $this->fallbackFiuuReturnResponse($request);
            }

            throw $exception;
        }

        foreach (self::BASE_HEADERS as $key => $value) {
            if (! $response->headers->has($key)) {
                $response->headers->set($key, $value);
            }
        }

        $this->applyFrameHeaders($response);

        return $response;
    }

    private function applyFrameHeaders(mixed $response): void
    {
        $frameAncestors = $this->normalizedFrameAncestors();

        if ($frameAncestors === []) {
            if (! $response->headers->has('X-Frame-Options')) {
                $response->headers->set('X-Frame-Options', 'DENY');
            }

            return;
        }

        $response->headers->remove('X-Frame-Options');

        $frameAncestorsDirective = 'frame-ancestors ' . implode(' ', $frameAncestors);
        $contentSecurityPolicy = trim((string) $response->headers->get('Content-Security-Policy', ''));

        if ($contentSecurityPolicy === '') {
            $response->headers->set('Content-Security-Policy', $frameAncestorsDirective);

            return;
        }

        if (str_contains(strtolower($contentSecurityPolicy), 'frame-ancestors')) {
            return;
        }

        $response->headers->set(
            'Content-Security-Policy',
            rtrim($contentSecurityPolicy, '; ') . '; ' . $frameAncestorsDirective
        );
    }

    private function normalizedFrameAncestors(): array
    {
        $origins = is_array(config('security.framing.allowed_origins', []))
            ? config('security.framing.allowed_origins', [])
            : [];

        $normalized = [];

        foreach ($origins as $origin) {
            $value = trim((string) $origin);

            if ($value === '') {
                continue;
            }

            if (in_array(strtolower($value), ['auto', 'same-domain', 'same_domain'], true)) {
                $normalized = [...$normalized, ...$this->autoFrameAncestors()];
                continue;
            }

            if (in_array(strtolower($value), ['self', "'self'"], true)) {
                $normalized[] = "'self'";
                continue;
            }

            if (in_array(strtolower($value), ['none', "'none'"], true)) {
                $normalized[] = "'none'";
                continue;
            }

            $normalized[] = $value;
        }

        return array_values(array_unique(array_filter($normalized)));
    }

    private function autoFrameAncestors(): array
    {
        $appUrl = trim((string) config('app.url', ''));

        if ($appUrl === '') {
            return ["'self'"];
        }

        $parts = parse_url($appUrl);
        $scheme = (string) ($parts['scheme'] ?? 'https');
        $host = strtolower((string) ($parts['host'] ?? ''));

        if ($host === '') {
            return ["'self'"];
        }

        $origin = $scheme . '://' . $host . (isset($parts['port']) ? ':' . $parts['port'] : '');
        $rootHost = $this->detectRootHost($host);

        $ancestors = ["'self'", $origin];

        if ($rootHost !== null) {
            $ancestors[] = $scheme . '://' . $rootHost;
            $ancestors[] = $scheme . '://*.' . $rootHost;
        }

        return array_values(array_unique($ancestors));
    }

    private function detectRootHost(string $host): ?string
    {
        if (filter_var($host, FILTER_VALIDATE_IP) || $host === 'localhost') {
            return null;
        }

        $segments = array_values(array_filter(explode('.', $host)));
        $count = count($segments);

        if ($count < 2) {
            return null;
        }

        $last = $segments[$count - 1];
        $secondLast = $segments[$count - 2];

        if (strlen($last) === 2 && strlen($secondLast) <= 3 && $count >= 3) {
            return implode('.', array_slice($segments, -3));
        }

        return implode('.', array_slice($segments, -2));
    }

    private function isFiuuReturnRequest(Request $request): bool
    {
        return $request->is('billing/gateways/fiuu/return');
    }

    private function fallbackFiuuReturnResponse(Request $request): Response
    {
        $reference = (string) ($request->input('RefNo') ?? $request->input('reference') ?? $request->input('orderid') ?? '');
        $target = '/billing';
        if ($reference !== '') {
            $target .= '?reference=' . rawurlencode($reference);
        }

        return new Response(
            sprintf(
                '<!DOCTYPE html><html><head><meta charset="utf-8"><meta http-equiv="refresh" content="0;url=%1$s"></head><body><script>window.location.replace(%2$s);</script><p>Redirecting to billing...</p><a href=%2$s>Continue</a></body></html>',
                htmlspecialchars($target, ENT_QUOTES, 'UTF-8'),
                json_encode($target, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            ),
            200,
            [
                'Content-Type' => 'text/html; charset=UTF-8',
            ]
        );
    }
}
