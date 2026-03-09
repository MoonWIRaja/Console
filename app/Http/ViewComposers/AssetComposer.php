<?php

namespace Pterodactyl\Http\ViewComposers;

use Illuminate\View\View;
use Pterodactyl\Services\Helpers\AssetHashService;
use Pterodactyl\Services\Auth\OAuth\OAuthProviderService;

class AssetComposer
{
    /**
     * AssetComposer constructor.
     */
    public function __construct(
        private AssetHashService $assetHashService,
        private OAuthProviderService $oauthProviders,
    ) {
    }

    /**
     * Provide access to the asset service in the views.
     */
    public function compose(View $view): void
    {
        $turnstileEnabled = (bool) config('turnstile.enabled', false);

        $view->with('asset', $this->assetHashService);
        $view->with('siteConfiguration', [
            'name' => config('app.name') ?? 'Pterodactyl',
            'logo' => asset(config('app.logo') ?: 'assets/svgs/pterodactyl.svg'),
            'locale' => config('app.locale') ?? 'en',
            'captcha' => [
                'enabled' => $turnstileEnabled,
                'provider' => 'turnstile',
                'siteKey' => config('turnstile.site_key') ?? '',
            ],
            'oauth' => $this->oauthProviders->getFrontendProviders(),
        ]);
    }
}
