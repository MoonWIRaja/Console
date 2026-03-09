<?php

namespace Pterodactyl\Http\Controllers\Api\Client;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Services\Auth\OAuth\OAuthProviderService;

class OAuthAccountController extends ClientApiController
{
    public function __construct(private OAuthProviderService $providers)
    {
        parent::__construct();
    }

    public function index(Request $request): array
    {
        $user = $request->user()->loadMissing('oauthAccounts');
        $accounts = $user->oauthAccounts->keyBy('provider');

        return [
            'data' => collect($this->providers->getManagementProviders())
                ->map(function (array $details, string $provider) use ($accounts) {
                    $account = $accounts->get($provider);

                    return [
                        'provider' => $provider,
                        'label' => $details['label'],
                        'enabled' => $details['enabled'],
                        'configured' => $details['configured'],
                        'available' => $details['available'],
                        'linked' => !is_null($account),
                        'link_url' => route('auth.oauth.redirect', ['provider' => $provider, 'intent' => 'link']),
                        'account' => $account ? [
                            'display_name' => $account->display_name,
                            'email' => $account->email,
                            'avatar' => $account->avatar,
                            'linked_at' => optional($account->created_at)->toAtomString(),
                            'updated_at' => optional($account->updated_at)->toAtomString(),
                        ] : null,
                    ];
                })
                ->values()
                ->all(),
        ];
    }

    public function delete(Request $request, string $provider): JsonResponse
    {
        if (!$this->providers->isKnownProvider($provider)) {
            abort(404);
        }

        $request->user()->oauthAccounts()
            ->where('provider', strtolower($provider))
            ->delete();

        return new JsonResponse([], Response::HTTP_NO_CONTENT);
    }
}
