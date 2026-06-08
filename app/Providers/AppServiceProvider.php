<?php

namespace Pterodactyl\Providers;

use Throwable;
use Pterodactyl\Models;
use Pterodactyl\Models\Security\SecurityAgent;
use Pterodactyl\Models\Subdomains\ServerSubdomain;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\URL;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Pterodactyl\Extensions\Themes\Theme;
use Illuminate\Database\Eloquent\Relations\Relation;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);

        View::share('appVersion', $this->versionData()['version'] ?? 'undefined');
        View::share('appIsGit', $this->versionData()['is_git'] ?? false);
        View::composer('layouts.admin', function ($view) {
            $adminSidebarSections = [
                [
                    'key' => 'basic',
                    'title' => 'Basic Administration',
                    'items' => [
                        ['route' => route('admin.index'), 'match' => 'admin.index', 'exact' => true, 'icon' => 'dashboard', 'label' => 'Overview'],
                        ['route' => route('admin.settings'), 'match' => 'admin.settings', 'icon' => 'settings', 'label' => 'Settings'],
                        ['route' => route('admin.oauth'), 'match' => 'admin.oauth', 'icon' => 'account_tree', 'label' => 'OAuth'],
                        ['route' => route('admin.discord'), 'match' => 'admin.discord', 'icon' => 'forum', 'label' => 'Discord'],
                        ['route' => route('admin.always-motd'), 'match' => 'admin.always-motd', 'icon' => 'stream', 'label' => 'Minecraft MOTD'],
                        ['route' => route('admin.down-detector'), 'match' => 'admin.down-detector', 'icon' => 'radar', 'label' => 'Down Detector'],
                        ['route' => route('admin.security'), 'match' => 'admin.security', 'icon' => 'shield', 'label' => 'Security'],
                        ['route' => route('admin.logs'), 'match' => 'admin.logs', 'icon' => 'receipt_long', 'label' => 'System Logs'],
                        ['route' => route('admin.api.index'), 'match' => 'admin.api', 'icon' => 'api', 'label' => 'Application API'],
                    ],
                ],
                [
                    'key' => 'management',
                    'title' => 'Management',
                    'items' => [
                        ['route' => route('admin.databases'), 'match' => 'admin.databases', 'icon' => 'dns', 'label' => 'Databases'],
                        ['route' => route('admin.subdomains.index'), 'match' => 'admin.subdomains', 'icon' => 'alternate_email', 'label' => 'Subdomains'],
                        ['route' => route('admin.locations'), 'match' => 'admin.locations', 'icon' => 'public', 'label' => 'Locations'],
                        ['route' => route('admin.nodes'), 'match' => 'admin.nodes', 'icon' => 'hub', 'label' => 'Nodes'],
                        ['route' => route('admin.servers'), 'match' => 'admin.servers', 'icon' => 'storage', 'label' => 'Servers'],
                        ['route' => route('admin.users'), 'match' => 'admin.users', 'icon' => 'group', 'label' => 'Users'],
                    ],
                ],
                [
                    'key' => 'service-management',
                    'title' => 'Service Management',
                    'items' => [
                        ['route' => route('admin.mounts'), 'match' => 'admin.mounts', 'icon' => 'inventory_2', 'label' => 'Mounts'],
                        ['route' => route('admin.nests'), 'match' => 'admin.nests', 'icon' => 'grid_view', 'label' => 'Nests'],
                    ],
                ],
            ];

            if (auth()->check() && auth()->user()->root_admin) {
                $adminSidebarSections[1]['items'][] = ['route' => route('admin.billing'), 'match' => 'admin.billing', 'icon' => 'payments', 'label' => 'Billing'];
                $adminSidebarSections[1]['items'][] = ['route' => route('admin.tickets'), 'match' => 'admin.tickets', 'icon' => 'support_agent', 'label' => 'Support'];
            }

            $view->with('adminSidebarSections', $adminSidebarSections);
        });

        Paginator::useBootstrap();

        // If the APP_URL value is set with https:// make sure we force it here. Theoretically
        // this should just work with the proxy logic, but there are a lot of cases where it
        // doesn't, and it triggers a lot of support requests, so lets just head it off here.
        //
        // @see https://github.com/pterodactyl/panel/issues/3623
        if (Str::startsWith(config('app.url') ?? '', 'https://')) {
            URL::forceScheme('https');
        }

        Relation::enforceMorphMap([
            'allocation' => Models\Allocation::class,
            'api_key' => Models\ApiKey::class,
            'backup' => Models\Backup::class,
            'database' => Models\Database::class,
            'egg' => Models\Egg::class,
            'egg_variable' => Models\EggVariable::class,
            'schedule' => Models\Schedule::class,
            'security_agent' => SecurityAgent::class,
            'server' => Models\Server::class,
            'server_subdomain' => ServerSubdomain::class,
            'ssh_key' => Models\UserSSHKey::class,
            'task' => Models\Task::class,
            'ticket' => Models\Ticket::class,
            'ticket_attachment' => Models\TicketAttachment::class,
            'ticket_message' => Models\TicketMessage::class,
            'user' => Models\User::class,
        ]);
    }

    /**
     * Register application service providers.
     */
    public function register(): void
    {
        // Only load the settings service provider if the environment
        // is configured to allow it.
        if (!config('pterodactyl.load_environment_only', false) && $this->app->environment() !== 'testing') {
            $this->app->register(SettingsServiceProvider::class);
        }

        $this->app->singleton('extensions.themes', function () {
            return new Theme();
        });
    }

    /**
     * Return version information for the footer.
     */
    protected function versionData(): array
    {
        $resolver = function () {
            if (file_exists(base_path('.git/HEAD'))) {
                $head = explode(' ', file_get_contents(base_path('.git/HEAD')));

                if (array_key_exists(1, $head)) {
                    $path = base_path('.git/' . trim($head[1]));
                }
            }

            if (isset($path) && file_exists($path)) {
                return [
                    'version' => substr(file_get_contents($path), 0, 8),
                    'is_git' => true,
                ];
            }

            return [
                'version' => config('app.version'),
                'is_git' => false,
            ];
        };

        try {
            return Cache::remember('git-version', 5, $resolver);
        } catch (Throwable) {
            // Avoid hard-failing auth/dashboard when file cache permissions are temporarily invalid.
            return $resolver();
        }
    }
}
