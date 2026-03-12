<?php

namespace Pterodactyl\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class SetSecurityHeaders
{
    /**
     * Ideally we move away from X-Frame-Options/X-XSS-Protection and implement a
     * proper standard CSP, but I can guarantee that will break for a lot of folks
     * using custom plugins and who knows what image embeds.
     *
     * We'll circle back to that at a later date when it can be more fully controlled
     * by the admin to support those cases without too much trouble.
     */
    private static array $headers = [
        'X-Frame-Options' => 'DENY',
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

        foreach (static::$headers as $key => $value) {
            if (! $response->headers->has($key)) {
                $response->headers->set($key, $value);
            }
        }

        return $response;
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
