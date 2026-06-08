<?php

namespace Pterodactyl\Providers;

use Pterodactyl\Models\User;
use Pterodactyl\Models\Node;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Subuser;
use Pterodactyl\Models\BillingOrder;
use Pterodactyl\Models\EggVariable;
use Pterodactyl\Models\BillingInvoice;
use Pterodactyl\Models\BillingNodeConfig;
use Pterodactyl\Models\BillingPayment;
use Pterodactyl\Models\BillingGatewayEvent;
use Pterodactyl\Models\BillingPaymentAttempt;
use Pterodactyl\Events\ActivityLogged;
use Pterodactyl\Events\Auth\FailedPasswordReset;
use Pterodactyl\Events\Server\InstallationCompleted;
use Pterodactyl\Observers\UserObserver;
use Pterodactyl\Observers\BillingLogObserver;
use Pterodactyl\Observers\NodeObserver;
use Pterodactyl\Observers\ServerObserver;
use Pterodactyl\Observers\SubuserObserver;
use Pterodactyl\Observers\BillingNodeConfigObserver;
use Pterodactyl\Listeners\TwoFactorListener;
use Pterodactyl\Listeners\RevocationListener;
use Pterodactyl\Listeners\AdminLogActivityRelayListener;
use Pterodactyl\Listeners\FailedPasswordResetListener;
use Pterodactyl\Listeners\SyncMinecraftLiveMotdListener;
use Pterodactyl\Observers\EggVariableObserver;
use Pterodactyl\Listeners\AuthenticationListener;
use Pterodactyl\Events\Server\Installed as ServerInstalledEvent;
use Pterodactyl\Notifications\ServerInstalled as ServerInstalledNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     */
    protected $listen = [
        ServerInstalledEvent::class => [ServerInstalledNotification::class],
        InstallationCompleted::class => [SyncMinecraftLiveMotdListener::class],
        FailedPasswordReset::class => [FailedPasswordResetListener::class],
        ActivityLogged::class => [AdminLogActivityRelayListener::class],
    ];

    protected $subscribe = [
        AuthenticationListener::class,
        RevocationListener::class,
        TwoFactorListener::class,
    ];

    protected static $shouldDiscoverEvents = false;

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        parent::boot();

        User::observe(UserObserver::class);
        Node::observe(NodeObserver::class);
        Server::observe(ServerObserver::class);
        Subuser::observe(SubuserObserver::class);
        EggVariable::observe(EggVariableObserver::class);
        BillingNodeConfig::observe(BillingNodeConfigObserver::class);
        BillingInvoice::observe(BillingLogObserver::class);
        BillingPayment::observe(BillingLogObserver::class);
        BillingPaymentAttempt::observe(BillingLogObserver::class);
        BillingOrder::observe(BillingLogObserver::class);
        BillingGatewayEvent::observe(BillingLogObserver::class);
    }
}
