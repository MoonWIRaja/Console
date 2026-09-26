<?php

namespace Pterodactyl\Http\Controllers\Api\Internal;

use Throwable;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Models\Ai\AiModel;
use Pterodactyl\Models\UserOAuthAccount;
use Pterodactyl\Services\Ai\AiToolbox;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Ai\AiChatService;
use Pterodactyl\Models\Ai\AiConversation;
use Pterodactyl\Exceptions\DisplayException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AiDiscordBridgeController extends Controller
{
    public function __construct(private AiChatService $chat)
    {
    }

    public function config(Request $request): JsonResponse
    {
        $this->authorizeBridge($request);

        return new JsonResponse([
            'enabled' => $this->enabled(),
            'bot_token' => (string) config('ai.discord.bot_token'),
            'name' => (string) (config('ai.persona_name') ?: 'Anney'),
            'channel_ids' => array_values(array_filter(array_map('trim', explode(',', (string) config('ai.discord.channel_ids'))))),
            'panel_url' => (string) config('app.url'),
        ]);
    }

    public function chat(Request $request): JsonResponse
    {
        $this->authorizeBridge($request);

        if (!$this->enabled()) {
            throw new HttpException(503, 'Anney on Discord is disabled.');
        }

        $data = $request->validate([
            'discord_user_id' => 'required|string|max:32',
            'discord_name' => 'required|string|max:100',
            'channel_id' => 'required|string|max:32',
            'content' => 'required|string|max:4000',
            'is_dm' => 'sometimes|boolean',
            'context' => 'sometimes|array|max:30',
            'context.*.author' => 'required|string|max:100',
            'context.*.reply_to' => 'nullable|string|max:100',
            'context.*.text' => 'required|string|max:1000',
            'context.*.at' => 'nullable|string|max:40',
            'replying_to' => 'sometimes|nullable|array',
            'replying_to.author' => 'required_with:replying_to|string|max:100',
            'replying_to.text' => 'nullable|string|max:1000',
        ]);

        $account = UserOAuthAccount::query()
            ->where('provider', 'discord')
            ->where('provider_id', $data['discord_user_id'])
            ->with('user')
            ->first();
        $user = $account?->user;

        $conversation = AiConversation::query()->firstOrCreate(
            ['channel' => 'discord', 'external_id' => $data['channel_id'] . ':' . $data['discord_user_id']],
            ['uuid' => (string) Str::uuid(), 'user_id' => $user?->id, 'title' => 'Discord chat'],
        );

        if ($conversation->user_id !== $user?->id) {
            $conversation->update(['user_id' => $user?->id]);
        }

        $model = null;
        if ($id = config('ai.discord.model_id')) {
            $model = AiModel::usable()->where('ai_models.id', (int) $id)->first();
        }

        $before = $conversation->messages()->max('id') ?? 0;

        try {
            $this->chat->send($conversation, $user, $data['content'], AiToolbox::MODE_ASK, $model, [
                'discord_name' => $data['discord_name'],
                'dm' => (bool) ($data['is_dm'] ?? false),
                'channel_context' => $data['context'] ?? [],
                'replying_to' => $data['replying_to'] ?? null,
            ]);
        } catch (DisplayException $exception) {
            return new JsonResponse(['reply' => 'Maaf, otak saya tengah tak sihat sekejap. (' . $exception->getMessage() . ')'], 200);
        } catch (Throwable $exception) {
            report($exception);

            return new JsonResponse(['reply' => 'Maaf, ada masalah teknikal. Cuba lagi sekejap lagi.'], 200);
        }

        $reply = $conversation->messages()
            ->where('id', '>', $before)
            ->where('role', 'assistant')
            ->whereNotNull('content')
            ->where('content', '!=', '')
            ->get()
            ->pluck('content')
            ->implode("\n\n");

        return new JsonResponse([
            'reply' => $reply !== '' ? $reply : '🙂',
            'linked' => (bool) $user,
        ]);
    }

    private function enabled(): bool
    {
        return filter_var(config('ai.enabled'), FILTER_VALIDATE_BOOLEAN)
            && filter_var(config('ai.discord.enabled'), FILTER_VALIDATE_BOOLEAN);
    }

    private function authorizeBridge(Request $request): void
    {
        $secret = (string) config('ai.discord.bridge_secret');
        $given = (string) $request->header('X-Anney-Secret', '');

        if ($secret === '' || !hash_equals($secret, $given)) {
            throw new HttpException(403, 'Invalid bridge secret.');
        }
    }
}
