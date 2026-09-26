<?php

namespace Pterodactyl\Http\Controllers\Admin\Ai;

use Throwable;
use Illuminate\View\View;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Pterodactyl\Models\Ai\AiModel;
use Pterodactyl\Models\Ai\AiSkill;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Pterodactyl\Models\Ai\AiProvider;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Models\Ai\AiUserMemory;
use Pterodactyl\Models\Ai\AiConversation;
use Pterodactyl\Services\Ai\AiProviderClient;
use Pterodactyl\Services\Ai\AiSkillImporter;
use Pterodactyl\Services\Ai\OAuth\AiOAuthService;
use Pterodactyl\Services\Ai\Catalog\ProviderCatalog;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Admin\Settings\AdminSettingsStoreService;

class AiController extends Controller
{
    private const TABS = ['general', 'providers', 'models', 'skills', 'discord'];

    public function __construct(
        private AlertsMessageBag $alert,
        private AdminSettingsStoreService $settings,
        private AiProviderClient $client,
        private AiOAuthService $oauth,
    ) {
    }

    public function index(Request $request): View
    {
        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'general';

        return view('admin.ai.index', [
            'tab' => $tab,
            'catalog' => ProviderCatalog::grouped(),
            'providers' => AiProvider::query()->withCount('models')->orderBy('priority')->orderBy('id')->get(),
            'models' => AiModel::query()->with('provider')->orderByDesc('enabled')->orderByDesc('is_default')->orderBy('priority')->orderBy('model_id')->get(),
            'usableModels' => AiModel::usable()->get(),
            'skills' => AiSkill::query()->orderBy('name')->get(),
            'stats' => [
                'conversations' => AiConversation::query()->count(),
                'panel_users' => AiConversation::query()->where('channel', 'panel')->distinct('user_id')->count('user_id'),
                'discord_chats' => AiConversation::query()->where('channel', 'discord')->count(),
                'memories' => AiUserMemory::query()->count(),
            ],
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $tab = $request->input('tab') === 'discord' ? 'discord' : 'general';

        if ($tab === 'general') {
            $data = $request->validate([
                'ai:enabled' => 'required|in:true,false',
                'ai:persona_name' => 'required|string|max:40',
                'ai:persona_prompt' => 'required|string|max:12000',
                'ai:max_steps' => 'required|integer|min:1|max:30',
            ]);
        } else {
            $data = $request->validate([
                'ai:discord:enabled' => 'required|in:true,false',
                'ai:discord:bot_token' => 'nullable|string|max:200',
                'ai:discord:model_id' => 'nullable|integer',
                'ai:discord:channel_ids' => ['nullable', 'string', 'max:2000', 'regex:/^[\d,\s]*$/'],
            ]);

            if (blank($data['ai:discord:bot_token'] ?? null)) {
                unset($data['ai:discord:bot_token']);
            }
            $data['ai:discord:model_id'] = $data['ai:discord:model_id'] ?? '';
            $data['ai:discord:channel_ids'] = preg_replace('/\s+/', '', (string) ($data['ai:discord:channel_ids'] ?? ''));

            if ($request->boolean('regenerate_secret') || blank(config('ai.discord.bridge_secret'))) {
                $data['ai:discord:bridge_secret'] = Str::random(64);
            }
        }

        $this->settings->save($data);

        if (isset($data['ai:discord:bridge_secret'])) {
            $envFile = '/opt/anney-bot/.env';
            $written = is_writable($envFile) && file_put_contents(
                $envFile,
                'PANEL_URL=' . rtrim((string) config('app.url'), '/') . "\nBRIDGE_SECRET=" . $data['ai:discord:bridge_secret'] . "\n",
                LOCK_EX,
            ) !== false;

            if (!$written) {
                $this->alert->warning('New bridge secret saved, but ' . $envFile . ' could not be updated. Update BRIDGE_SECRET there and run: systemctl restart anney-bot')->flash();

                return redirect()->route('admin.ai', ['tab' => $tab]);
            }
        }

        $this->alert->success('AI settings saved.')->flash();

        return redirect()->route('admin.ai', ['tab' => $tab]);
    }

    public function storeProvider(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'preset' => 'required|string',
            'api_key' => 'required|string|max:4096',
            'account_id' => 'nullable|string|max:64|alpha_dash',
            'label' => 'nullable|string|max:60',
        ]);

        $definition = ProviderCatalog::get($data['preset']);
        if (!$definition || $definition['flow'] !== 'apikey') {
            return $this->fail('That provider does not use an API key.', 'providers');
        }
        if (str_contains($definition['base_url'], '{accountId}') && blank($data['account_id'] ?? null)) {
            return $this->fail($definition['name'] . ' also needs your Account ID.', 'providers');
        }

        $provider = AiProvider::query()->create([
            'name' => $definition['name'] . (filled($data['label'] ?? null) ? ' (' . $data['label'] . ')' : ''),
            'account' => $data['label'] ?? null,
            'preset' => $data['preset'],
            'base_url' => $definition['base_url'],
            'api_key' => $data['api_key'],
            'settings' => filled($data['account_id'] ?? null) ? ['account_id' => $data['account_id']] : null,
            'priority' => 50,
        ]);

        return $this->syncProvider($provider);
    }

    public function oauthStart(Request $request, string $preset): JsonResponse
    {
        try {
            return new JsonResponse($this->oauth->start($preset, url('/admin/ai/oauth/callback')));
        } catch (Throwable $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 422);
        }
    }

    public function oauthPoll(string $session): JsonResponse
    {
        try {
            $result = $this->oauth->poll($session);
        } catch (Throwable $exception) {
            return new JsonResponse(['status' => 'error', 'message' => $exception->getMessage()]);
        }

        if ($result['status'] === 'done') {
            $provider = $result['provider'];
            $this->syncModels($provider);
            $this->alert->success($provider->name . ' connected. Enable the models you want in the Models tab.')->flash();

            return new JsonResponse(['status' => 'done', 'redirect' => route('admin.ai', ['tab' => 'models'])]);
        }

        return new JsonResponse($result);
    }

    public function oauthComplete(Request $request, string $session): RedirectResponse
    {
        $data = $request->validate(['url' => 'required|string|max:8000']);

        try {
            $provider = $this->oauth->complete($session, $data['url']);
        } catch (Throwable $exception) {
            return $this->fail('Login failed: ' . $exception->getMessage(), 'providers');
        }

        return $this->syncProvider($provider);
    }

    public function oauthCallback(Request $request, string $session): RedirectResponse
    {
        if (!$this->oauth->sessionProvider($session)) {
            return $this->fail('This login session expired. Start again.', 'providers');
        }

        try {
            $provider = $this->oauth->complete($session, $request->fullUrl());
        } catch (Throwable $exception) {
            return $this->fail('Login failed: ' . $exception->getMessage(), 'providers');
        }

        return $this->syncProvider($provider);
    }

    public function updateProvider(Request $request, AiProvider $provider): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'api_key' => 'nullable|string|max:4096',
            'enabled' => 'required|in:0,1',
            'priority' => 'required|integer|min:0|max:10000',
        ]);

        $provider->fill([
            'name' => $data['name'],
            'enabled' => (bool) $data['enabled'],
            'priority' => $data['priority'],
        ]);

        if (($provider->definition()['flow'] ?? null) === 'apikey' && filled($data['api_key'] ?? null)) {
            $provider->api_key = $data['api_key'];
        }

        $provider->save();
        $this->alert->success('Account updated.')->flash();

        return redirect()->route('admin.ai', ['tab' => 'providers']);
    }

    public function destroyProvider(AiProvider $provider): RedirectResponse
    {
        $provider->delete();
        $this->alert->success('Account disconnected and its models removed.')->flash();

        return redirect()->route('admin.ai', ['tab' => 'providers']);
    }

    public function syncProvider(AiProvider $provider): RedirectResponse
    {
        try {
            [$found, $added] = $this->syncModels($provider);
        } catch (Throwable $exception) {
            return $this->fail($provider->name . ' was connected, but its models could not be loaded: ' . $exception->getMessage(), 'providers');
        }

        $this->alert->success(sprintf('%s: %d models available (%d new). Enable the ones users may pick in the Models tab.', $provider->name, $found, $added))->flash();

        return redirect()->route('admin.ai', ['tab' => 'models']);
    }

    private function syncModels(AiProvider $provider): array
    {
        $found = $this->client->listModels($provider);
        $added = 0;
        foreach ($found as $item) {
            $model = AiModel::query()->firstOrCreate(
                ['provider_id' => $provider->id, 'model_id' => $item['id']],
                ['label' => $item['label'], 'enabled' => false, 'supports_tools' => true],
            );
            $added += $model->wasRecentlyCreated ? 1 : 0;
        }
        $provider->update(['models_synced_at' => now()]);

        return [count($found), $added];
    }

    public function updateModel(Request $request, AiModel $model): RedirectResponse
    {
        $data = $request->validate([
            'label' => 'nullable|string|max:80',
            'enabled' => 'required|in:0,1',
            'supports_tools' => 'required|in:0,1',
            'priority' => 'required|integer|min:0|max:10000',
        ]);

        $model->update([
            'label' => $data['label'] ?: null,
            'enabled' => (bool) $data['enabled'],
            'supports_tools' => (bool) $data['supports_tools'],
            'priority' => $data['priority'],
        ]);

        if (!$model->enabled && $model->is_default) {
            $model->update(['is_default' => false]);
        }

        $this->alert->success('Model updated.')->flash();

        return redirect()->route('admin.ai', ['tab' => 'models']);
    }

    public function defaultModel(AiModel $model): RedirectResponse
    {
        AiModel::query()->where('is_default', true)->update(['is_default' => false]);
        $model->update(['is_default' => true, 'enabled' => true]);
        $this->alert->success($model->model_id . ' is now the default model.')->flash();

        return redirect()->route('admin.ai', ['tab' => 'models']);
    }

    public function testModel(AiModel $model): RedirectResponse
    {
        try {
            $reply = $this->client->chat($model, [
                ['role' => 'user', 'content' => 'Reply with exactly: OK'],
            ]);
        } catch (Throwable $exception) {
            return $this->fail('Test failed: ' . $exception->getMessage(), 'models');
        }

        $this->alert->success('Test OK. ' . $model->model_id . ' replied: ' . Str::limit($reply['content'], 120))->flash();

        return redirect()->route('admin.ai', ['tab' => 'models']);
    }

    public function saveSkill(Request $request, ?AiSkill $skill = null): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:80',
            'slug' => 'nullable|alpha_dash|max:64',
            'description' => 'nullable|string|max:500',
            'content' => 'required|string|max:60000',
            'scope' => 'required|in:' . implode(',', AiSkill::SCOPES),
            'enabled' => 'required|in:0,1',
        ]);

        $slug = Str::slug($data['slug'] ?: $data['name']);
        if (AiSkill::query()->where('slug', $slug)->when($skill?->exists, fn ($q) => $q->where('id', '!=', $skill->id))->exists()) {
            return $this->fail('A skill with the slug "' . $slug . '" already exists.', 'skills');
        }

        $skill = $skill?->exists ? $skill : new AiSkill();
        $skill->fill([
            'name' => $data['name'],
            'slug' => $slug,
            'description' => $data['description'],
            'content' => $data['content'],
            'scope' => $data['scope'],
            'enabled' => (bool) $data['enabled'],
        ])->save();

        $this->alert->success('Skill "' . $skill->name . '" saved.')->flash();

        return redirect()->route('admin.ai', ['tab' => 'skills']);
    }

    public function importSkill(Request $request, AiSkillImporter $importer): JsonResponse
    {
        $data = $request->validate([
            'file' => 'required|file|max:2048',
            'scope' => 'required|in:' . implode(',', AiSkill::SCOPES),
        ]);

        @set_time_limit(180);

        try {
            $result = $importer->import($data['file'], $data['scope']);
        } catch (Throwable $exception) {
            return new JsonResponse(['message' => $exception->getMessage()], 422);
        }

        $this->alert->success('Skill "' . $result['skill']->name . '" was reviewed and added.')->flash();

        return new JsonResponse([
            'message' => 'Added "' . $result['skill']->name . '". ' . $result['notes'],
            'skill' => $result['skill']->id,
        ]);
    }

    public function destroySkill(AiSkill $skill): RedirectResponse
    {
        $skill->delete();
        $this->alert->success('Skill deleted.')->flash();

        return redirect()->route('admin.ai', ['tab' => 'skills']);
    }

    private function fail(string $message, string $tab): RedirectResponse
    {
        $this->alert->danger($message)->flash();

        return redirect()->route('admin.ai', ['tab' => $tab])->withInput();
    }
}
