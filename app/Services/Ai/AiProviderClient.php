<?php

namespace Pterodactyl\Services\Ai;

use Throwable;
use GuzzleHttp\Client;
use Pterodactyl\Models\Ai\AiModel;
use Pterodactyl\Models\Ai\AiProvider;
use Pterodactyl\Services\Ai\OAuth\AiOAuthService;

class AiProviderClient
{
    public function __construct(private AiRequestExecutor $executor, private AiOAuthService $oauth)
    {
    }

    /**
     * Models for a connected account: the catalog list plus, where 9router
     * fetches them live (OpenRouter/Kilo free lists), the current live list.
     *
     * @return array<int, array{id: string, label: ?string}>
     */
    public function listModels(AiProvider $provider): array
    {
        $definition = $provider->definition() ?? [];
        $models = [];
        foreach ($definition['models'] ?? [] as $model) {
            $models[$model['id']] = ['id' => $model['id'], 'label' => $model['name'] ?? null];
        }

        $fetcher = $definition['models_fetcher'] ?? null;
        if ($fetcher) {
            foreach ($this->fetchLive($provider, $fetcher['url'], $fetcher['type'] ?? '') as $model) {
                $models[$model['id']] ??= $model;
            }
        }

        ksort($models);

        return array_values($models);
    }

    private function fetchLive(AiProvider $provider, string $url, string $type): array
    {
        $headers = ['Accept' => 'application/json'];
        $credentials = rescue(fn () => $this->oauth->credentials($provider), [], false);
        $token = $credentials['api_key'] ?? $credentials['access_token'] ?? null;
        if ($type === 'anthropic') {
            $headers += ['x-api-key' => (string) $token, 'anthropic-version' => '2023-06-01'];
        } elseif ($token) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        try {
            $response = (new Client(['timeout' => 20, 'http_errors' => false]))->get($url, ['headers' => $headers]);
            $items = json_decode((string) $response->getBody(), true)['data'] ?? [];
        } catch (Throwable) {
            return [];
        }

        $out = [];
        foreach ($items as $item) {
            $id = (string) ($item['id'] ?? '');
            if ($id === '') {
                continue;
            }
            if (str_contains($type, 'free')) {
                $pricing = $item['pricing'] ?? [];
                $free = str_ends_with($id, ':free') || str_ends_with($id, '/free')
                    || ((string) ($pricing['prompt'] ?? '1') === '0' && (string) ($pricing['completion'] ?? '1') === '0');
                if (!$free) {
                    continue;
                }
            }
            $out[] = ['id' => $id, 'label' => $item['display_name'] ?? $item['name'] ?? null];
        }

        return $out;
    }

    public function chat(AiModel $model, array $messages, array $tools = []): array
    {
        return $this->executor->chat($model, $messages, $tools);
    }
}
