<?php

namespace Pterodactyl\Http\Middleware;

use Illuminate\Http\Request;
use Prologue\Alerts\AlertsMessageBag;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class AdminAuthenticate
{
    public function __construct(private AlertsMessageBag $alert)
    {
    }

    /**
     * Handle an incoming request.
     *
     * @throws AccessDeniedHttpException
     */
    public function handle(Request $request, \Closure $next): mixed
    {
        $user = $request->user();
        if (!$user) {
            if ($request->expectsJson() || $request->isJson()) {
                throw new AccessDeniedHttpException();
            }

            return redirect()->guest(route('auth.login'));
        }

        if (!$user->root_admin) {
            if ($request->expectsJson() || $request->isJson()) {
                throw new AccessDeniedHttpException();
            }

            $this->alert->danger('This area requires a root administrator account.')->flash();

            return redirect()->route('index');
        }

        return $next($request);
    }
}
