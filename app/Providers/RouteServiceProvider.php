<?php

namespace Pterodactyl\Providers;

use Illuminate\Http\Request;
use Pterodactyl\Models\Database;
use Pterodactyl\Enum\ResourceLimit;
use Illuminate\Support\Facades\Route;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Pterodactyl\Http\Controllers\Billing\BclGatewayController;
use Pterodactyl\Http\Controllers\Api\Internal\SecurityAgentController;
use Pterodactyl\Http\Controllers\Api\Internal\TicketDiscordBridgeController;
use Pterodactyl\Http\Controllers\Billing\BillingDocumentController;
use Pterodactyl\Http\Controllers\Billing\FiuuGatewayController;
use Pterodactyl\Http\Controllers\Billing\StripeGatewayController;
use Pterodactyl\Http\Controllers\Tickets\DiscordInteractionController;
use Pterodactyl\Http\Controllers\Tickets\TicketAttachmentController;
use Pterodactyl\Http\Middleware\EnforceSecurityPolicies;
use Pterodactyl\Http\Middleware\TrimStrings;
use Pterodactyl\Http\Middleware\AdminAuthenticate;
use Pterodactyl\Http\Middleware\RequireTwoFactorAuthentication;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    protected const FILE_PATH_REGEX = '/^\/api\/client\/servers\/([a-z0-9-]{36})\/files(\/?$|\/(.)*$)/i';

    /**
     * Define your route model bindings, pattern filters, etc.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();

        // Disable trimming string values when requesting file information — it isn't helpful
        // and messes up the ability to actually open a directory that ends with a space.
        TrimStrings::skipWhen(function (Request $request) {
            return preg_match(self::FILE_PATH_REGEX, $request->getPathInfo()) === 1;
        });

        // This is needed to make use of the "resolveRouteBinding" functionality in the
        // model. Without it you'll never trigger that logic flow thus resulting in a 404
        // error because we request databases with a HashID, and not with a normal ID.
        Route::model('database', Database::class);

        $this->routes(function () {
            Route::post('/tickets/discord/interactions', DiscordInteractionController::class)
                ->middleware(EnforceSecurityPolicies::class)
                ->name('tickets.discord.interactions');

            Route::post('/billing/gateways/bcl/webhook', [BclGatewayController::class, 'webhook'])
                ->middleware(EnforceSecurityPolicies::class)
                ->name('billing.gateway.bcl.webhook');

            Route::post('/billing/gateways/fiuu/callback', [FiuuGatewayController::class, 'callback'])
                ->middleware(EnforceSecurityPolicies::class)
                ->name('billing.gateway.fiuu.callback');

            Route::match(['GET', 'POST'], '/billing/gateways/fiuu/return', [FiuuGatewayController::class, 'return'])
                ->name('billing.gateway.fiuu.return');

            Route::post('/billing/gateways/stripe/webhook', [StripeGatewayController::class, 'webhook'])
                ->middleware(EnforceSecurityPolicies::class)
                ->name('billing.gateway.stripe.webhook');

            Route::get('/billing/gateways/stripe/return', [StripeGatewayController::class, 'return'])
                ->name('billing.gateway.stripe.return');

            Route::middleware('web')->group(function () {
                Route::get('/billing/documents/invoices/{billingInvoice}', [BillingDocumentController::class, 'invoice'])
                    ->name('billing.documents.invoices.show');
                Route::get('/billing/documents/invoices/{billingInvoice}/raw', [BillingDocumentController::class, 'invoiceRaw'])
                    ->name('billing.documents.invoices.raw');

                Route::get('/billing/documents/payments/{billingPayment}/receipt', [BillingDocumentController::class, 'receipt'])
                    ->name('billing.documents.payments.receipt');
                Route::get('/billing/documents/payments/{billingPayment}/receipt/raw', [BillingDocumentController::class, 'receiptRaw'])
                    ->name('billing.documents.payments.receipt.raw');

                Route::middleware(['auth.session', RequireTwoFactorAuthentication::class])
                    ->get('/tickets/attachments/{ticketAttachment}', [TicketAttachmentController::class, 'download'])
                    ->name('tickets.attachments.download');

                Route::middleware(['auth.session', RequireTwoFactorAuthentication::class])
                    ->get('/billing/tickets/attachments/{ticketAttachment}', [TicketAttachmentController::class, 'download'])
                    ->name('tickets.attachments.download.legacy');

                Route::get('/billing/gateways/fiuu/checkout/{checkoutReference}', [FiuuGatewayController::class, 'checkout'])
                    ->name('billing.gateway.fiuu.checkout');

                Route::middleware(['auth.session', RequireTwoFactorAuthentication::class])
                    ->group(base_path('routes/base.php'));

                Route::middleware(['auth.session', RequireTwoFactorAuthentication::class, AdminAuthenticate::class])
                    ->prefix('/admin')
                    ->group(base_path('routes/admin.php'));

                Route::prefix('/oauth')->group(base_path('routes/oauth.php'));
                Route::middleware('guest')->prefix('/auth')->group(base_path('routes/auth.php'));
            });

            Route::prefix('/api/internal')->middleware(EnforceSecurityPolicies::class)->group(function () {
                Route::post('/tickets/discord/interactions', [TicketDiscordBridgeController::class, 'interactions']);
                Route::post('/tickets/discord/events', [TicketDiscordBridgeController::class, 'events']);
                Route::post('/tickets/discord/heartbeat', [TicketDiscordBridgeController::class, 'heartbeat']);
                Route::post('/security/agents/heartbeat', [SecurityAgentController::class, 'heartbeat']);
                Route::post('/security/agents/report', [SecurityAgentController::class, 'report']);
                Route::post('/security/agents/pull-actions', [SecurityAgentController::class, 'pullActions']);
                Route::post('/security/agents/action-result', [SecurityAgentController::class, 'actionResult']);
            });

            Route::middleware(['api', RequireTwoFactorAuthentication::class])->group(function () {
                Route::middleware(['application-api', 'throttle:api.application'])
                    ->prefix('/api/application')
                    ->scopeBindings()
                    ->group(base_path('routes/api-application.php'));

                Route::middleware(['client-api', 'throttle:api.client'])
                    ->prefix('/api/client')
                    ->scopeBindings()
                    ->group(base_path('routes/api-client.php'));
            });

            Route::middleware('daemon')
                ->prefix('/api/remote')
                ->scopeBindings()
                ->group(base_path('routes/api-remote.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        // Authentication rate limiting. For login and checkpoint endpoints we'll apply
        // a limit of 10 requests per minute, for the forgot password endpoint apply a
        // limit of two per minute for the requester so that there is less ability to
        // trigger email spam.
        RateLimiter::for('authentication', function (Request $request) {
            $profile = strtolower((string) config('security.rate_profile', 'balanced'));
            $routeName = (string) ($request->route()?->getName() ?? $request->path());
            $key = sprintf('%s|%s', $request->ip(), $routeName);

            $limits = match ($profile) {
                'strict' => [
                    'login_per_minute' => 8,
                    'signup_per_10m' => 4,
                    'reset_per_10m' => 4,
                    'verify_per_10m' => 6,
                ],
                'lenient' => [
                    'login_per_minute' => 18,
                    'signup_per_10m' => 10,
                    'reset_per_10m' => 10,
                    'verify_per_10m' => 12,
                ],
                default => [
                    'login_per_minute' => 12,
                    'signup_per_10m' => 6,
                    'reset_per_10m' => 6,
                    'verify_per_10m' => 8,
                ],
            };

            if ($request->route()?->named('auth.login-checkpoint') || $request->route()?->named('auth.post.login')) {
                return [
                    Limit::perMinute($limits['login_per_minute'])->by($key),
                ];
            }

            if ($request->route()?->named('auth.post.signup') || $request->route()?->named('auth.post.forgot-password')) {
                return [
                    Limit::perMinutes(10, $limits['signup_per_10m'])->by($key),
                ];
            }

            if ($request->route()?->named('auth.reset-password')) {
                return [
                    Limit::perMinutes(10, $limits['reset_per_10m'])->by($key),
                ];
            }

            if ($request->route()?->named('auth.post.signup.verify')) {
                return [
                    Limit::perMinutes(10, $limits['verify_per_10m'])->by($key),
                ];
            }

            return [
                Limit::perMinute($limits['login_per_minute'])->by($key),
            ];
        });

        // Configure the throttles for both the application and client APIs below.
        // This is configurable per-instance in "config/http.php". By default this
        // limiter will be tied to the specific request user, and falls back to the
        // request IP if there is no request user present for the key.
        //
        // This means that an authenticated API user cannot use IP switching to get
        // around the limits.
        RateLimiter::for('api.client', function (Request $request) {
            $key = optional($request->user())->uuid ?: $request->ip();

            return Limit::perMinutes(
                config('http.rate_limit.client_period'),
                config('http.rate_limit.client')
            )->by($key);
        });

        RateLimiter::for('api.application', function (Request $request) {
            $key = optional($request->user())->uuid ?: $request->ip();

            return Limit::perMinutes(
                config('http.rate_limit.application_period'),
                config('http.rate_limit.application')
            )->by($key);
        });

        ResourceLimit::boot();
    }
}
