<?php

namespace Pterodactyl\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Pterodactyl\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\Response;

class UpdateLastSeen
{
    /**
     * Keep session-authenticated user activity reasonably fresh without writing
     * to the database on every request or mutating the user's updated_at value.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $user = $request->user();
        if (!$user instanceof User || $request->bearerToken() || $user->currentAccessToken()) {
            return $response;
        }

        if (!Schema::hasColumn($user->getTable(), 'last_seen_at')) {
            return $response;
        }

        $now = now();
        $cutoff = $now->copy()->subMinute();

        if ($user->last_seen_at?->greaterThan($cutoff)) {
            return $response;
        }

        User::query()
            ->whereKey($user->getKey())
            ->where(function (Builder $query) use ($cutoff) {
                $query->whereNull('last_seen_at')->orWhere('last_seen_at', '<=', $cutoff);
            })
            ->update(['last_seen_at' => $now]);

        $user->forceFill(['last_seen_at' => $now]);

        return $response;
    }
}
