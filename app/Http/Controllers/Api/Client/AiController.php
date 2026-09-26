<?php

namespace Pterodactyl\Http\Controllers\Api\Client;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Models\Ai\AiModel;
use Pterodactyl\Models\Ai\AiMessage;
use Pterodactyl\Services\Ai\AiToolbox;
use Pterodactyl\Services\Ai\AiChatService;
use Pterodactyl\Models\Ai\AiConversation;
use Pterodactyl\Models\Ai\AiPendingAction;
use Pterodactyl\Exceptions\DisplayException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AiController extends ClientApiController
{
    public function __construct(private AiChatService $chat)
    {
        parent::__construct();
    }

    public function config(): JsonResponse
    {
        return new JsonResponse([
            'enabled' => $this->enabled(),
            'name' => (string) (config('ai.persona_name') ?: 'Anney'),
            // Only model names reach users: provider account names contain the admin's login email.
            // The same model on several accounts is listed once; fallback still rotates accounts.
            'models' => $this->enabled() ? AiModel::usable()->get()->unique('model_id')->map(fn (AiModel $m) => [
                'id' => $m->id,
                'name' => $m->displayName(),
                'provider' => '',
                'default' => $m->is_default,
            ])->values() : [],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->assertEnabled();

        $conversations = AiConversation::query()
            ->where('user_id', $request->user()->id)
            ->where('channel', 'panel')
            ->with('server:id,uuid,name')
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get();

        return new JsonResponse(['data' => $conversations->map(fn ($c) => $this->summary($c))->values()]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->assertEnabled();
        $data = $request->validate(['server' => 'nullable|string|max:64']);

        $server = null;
        if (!empty($data['server'])) {
            $server = $request->user()->accessibleServers()
                ->where(fn ($q) => $q->where('servers.uuid', $data['server'])->orWhere('servers.uuidShort', $data['server']))
                ->first();

            if ($server && !$request->user()->can(\Pterodactyl\Models\Permission::ACTION_AI_READ, $server)) {
                throw new DisplayException('You do not have permission to use the AI assistant on this server. Ask the server owner to grant "AI: read" on the Users page.');
            }
        }

        $conversation = AiConversation::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $request->user()->id,
            'server_id' => $server?->id,
            'channel' => 'panel',
        ]);

        return new JsonResponse($this->full($conversation->fresh()), 201);
    }

    public function show(Request $request, string $conversation): JsonResponse
    {
        $this->assertEnabled();

        return new JsonResponse($this->full($this->find($request, $conversation)));
    }

    public function destroy(Request $request, string $conversation): JsonResponse
    {
        $this->find($request, $conversation)->delete();

        return new JsonResponse([], 204);
    }

    public function message(Request $request, string $conversation): JsonResponse
    {
        $this->assertEnabled();
        $data = $request->validate([
            'content' => 'required|string|max:8000',
            'mode' => 'required|in:ask,agent',
            'model' => 'nullable|integer',
        ]);

        $conversation = $this->find($request, $conversation);
        $this->chat->send($conversation, $request->user(), $data['content'], $data['mode'], $this->model($data['model'] ?? null));

        return new JsonResponse($this->full($conversation->fresh()));
    }

    public function edit(Request $request, string $conversation, int $message): JsonResponse
    {
        $this->assertEnabled();
        $data = $request->validate([
            'content' => 'required|string|max:8000',
            'mode' => 'required|in:ask,agent',
            'model' => 'nullable|integer',
        ]);

        $conversation = $this->find($request, $conversation);
        $original = AiMessage::query()->where('conversation_id', $conversation->id)->findOrFail($message);

        $this->chat->edit($conversation, $original, $request->user(), $data['content'], $data['mode'], $this->model($data['model'] ?? null));

        return new JsonResponse($this->full($conversation->fresh()));
    }

    public function action(Request $request, string $action): JsonResponse
    {
        $this->assertEnabled();
        $data = $request->validate([
            'decision' => 'required|in:approve,reject',
            'mode' => 'required|in:ask,agent',
            'model' => 'nullable|integer',
        ]);

        $action = AiPendingAction::query()->where('uuid', $action)->with(['conversation', 'server'])->firstOrFail();
        if ($action->conversation->user_id !== $request->user()->id || $action->conversation->channel !== 'panel') {
            throw new NotFoundHttpException();
        }

        $this->chat->resolve($action, $request->user(), $data['decision'] === 'approve', $data['mode'], $this->model($data['model'] ?? null));

        return new JsonResponse($this->full($action->conversation->fresh()));
    }

    private function enabled(): bool
    {
        return filter_var(config('ai.enabled'), FILTER_VALIDATE_BOOLEAN);
    }

    private function assertEnabled(): void
    {
        if (!$this->enabled()) {
            throw new DisplayException('The AI assistant is currently disabled.');
        }
    }

    private function model(?int $id): ?AiModel
    {
        return $id ? AiModel::usable()->where('ai_models.id', $id)->first() : null;
    }

    private function find(Request $request, string $uuid): AiConversation
    {
        $conversation = AiConversation::query()->where('uuid', $uuid)->with('server:id,uuid,name')->first();

        if (!$conversation || $conversation->user_id !== $request->user()->id || $conversation->channel !== 'panel') {
            throw new NotFoundHttpException();
        }

        return $conversation;
    }

    private function summary(AiConversation $conversation): array
    {
        return [
            'uuid' => $conversation->uuid,
            'title' => $conversation->title,
            'server' => $conversation->server ? [
                'id' => substr($conversation->server->uuid, 0, 8),
                'name' => $conversation->server->name,
            ] : null,
            'updated_at' => $conversation->updated_at?->toIso8601String(),
        ];
    }

    private function full(AiConversation $conversation): array
    {
        $actions = $conversation->pendingActions()->with('server:id,uuid,name')->get()->keyBy('tool_call_id');
        $items = [];

        foreach ($conversation->messages()->get() as $message) {
            /** @var AiMessage $message */
            if ($message->role === 'tool') {
                continue;
            }

            $item = [
                'id' => $message->id,
                'role' => $message->role,
                'content' => (string) ($message->content ?? ''),
                'created_at' => $message->created_at?->toIso8601String(),
                'model' => $message->meta['model'] ?? null,
                'tools' => [],
                'actions' => [],
            ];

            foreach ($message->tool_calls ?? [] as $call) {
                $name = $call['function']['name'] ?? '';
                $action = $actions->get($call['id'] ?? '');

                if ($action && AiToolbox::isWriteTool($name)) {
                    $args = $action->arguments;
                    $item['actions'][] = [
                        'uuid' => $action->uuid,
                        'tool' => $action->tool,
                        'status' => $action->status,
                        'result' => $action->result,
                        'server' => $action->server?->name,
                        'arguments' => array_diff_key($args, ['_original' => true]),
                        'original' => $args['_original'] ?? null,
                    ];
                } else {
                    $item['tools'][] = $name;
                }
            }

            if ($item['content'] === '' && $item['tools'] === [] && $item['actions'] === []) {
                continue;
            }

            $items[] = $item;
        }

        return array_merge($this->summary($conversation), ['messages' => $items]);
    }
}
