<?php

namespace Pterodactyl\Services\Ai;

use Throwable;
use Illuminate\Support\Str;
use Pterodactyl\Models\User;
use Illuminate\Support\Facades\Cache;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Ai\AiModel;
use Pterodactyl\Models\Ai\AiMessage;
use Pterodactyl\Models\Ai\AiConversation;
use Pterodactyl\Models\Ai\AiPendingAction;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;

class AiChatService
{
    private const HISTORY_LIMIT = 40;
    private const MAX_FALLBACKS = 5;

    private bool $privateChannel = true;
    private const COOLDOWN_KEY = 'ai-model-cooldown:';
    private const COOLDOWN_MINUTES = 10;
    private const ORIGINAL_PREVIEW_LIMIT = 200 * 1024;

    public function __construct(
        private AiProviderClient $client,
        private AiToolbox $toolbox,
        private AiPromptBuilder $prompts,
        private DaemonFileRepository $files,
    ) {
    }

    /**
     * Handles a new user message and runs the model until it answers or needs approval.
     */
    public function send(AiConversation $conversation, ?User $user, string $text, string $mode, ?AiModel $model, array $extra = []): void
    {
        $this->rejectOutstanding($conversation, 'The user sent a new message instead of approving this action, so it was cancelled.');

        AiMessage::query()->create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $text,
            'meta' => ['mode' => $mode],
        ]);

        if ($conversation->title === 'New chat') {
            $conversation->title = Str::limit(preg_replace('/\s+/', ' ', $text), 60);
        }
        $conversation->touch();
        $conversation->save();

        $this->run($conversation, $user, $mode, $model, $extra);
    }

    /**
     * Replaces a user message: everything from that message onwards is removed
     * (including unapproved actions) and the model answers the edited text.
     */
    public function edit(AiConversation $conversation, AiMessage $message, ?User $user, string $text, string $mode, ?AiModel $model): void
    {
        if ($message->conversation_id !== $conversation->id || $message->role !== 'user') {
            throw new DisplayException('Only your own messages in this chat can be edited.');
        }

        $removed = AiMessage::query()->where('conversation_id', $conversation->id)->where('id', '>=', $message->id)->get();
        $callIds = $removed->flatMap(fn (AiMessage $m) => array_column($m->tool_calls ?? [], 'id'))->all();

        if ($callIds !== []) {
            $conversation->pendingActions()->whereIn('tool_call_id', $callIds)->where('status', 'pending')->delete();
        }
        AiMessage::query()->whereIn('id', $removed->pluck('id'))->delete();

        $this->send($conversation, $user, $text, $mode, $model);
    }

    /**
     * Approves or rejects a pending write action and lets the model continue.
     */
    public function resolve(AiPendingAction $action, User $user, bool $approve, string $mode, ?AiModel $model): void
    {
        if ($action->status !== 'pending') {
            throw new DisplayException('This action has already been handled.');
        }

        $conversation = $action->conversation;

        if ($approve) {
            try {
                $result = $this->toolbox->runWrite($user, $action->server, $action->tool, $this->cleanArgs($action->arguments));
                $action->status = 'executed';
            } catch (Throwable $exception) {
                $result = 'Failed: ' . $exception->getMessage();
                $action->status = 'failed';
            }
        } else {
            $result = 'The user rejected this action. Do not retry it unless they ask; ask what they would prefer instead.';
            $action->status = 'rejected';
        }

        $action->result = $result;
        $action->save();

        $this->storeToolResult($conversation, $action->tool_call_id, $action->tool, $result);

        if ($conversation->pendingActions()->where('status', 'pending')->exists()) {
            return;
        }

        $this->run($conversation, $user, $mode, $model);
    }

    private function run(AiConversation $conversation, ?User $user, string $mode, ?AiModel $model, array $extra = []): void
    {
        $channel = $conversation->channel;
        // Controllers eager-load a trimmed server row for display; tools need owner_id and node_id.
        $server = $conversation->server_id ? Server::query()->find($conversation->server_id) : null;
        if ($server && (!$user || !$user->can(\Pterodactyl\Models\Permission::ACTION_AI_READ, $server))) {
            $server = null;
        }
        $this->privateChannel = $channel !== 'discord' || !empty($extra['dm']);
        $tools = $user ? $this->toolbox->definitions($channel, $mode, $this->privateChannel) : $this->toolbox->publicDefinitions();
        $system = $this->prompts->build($user, $server, $channel, $mode, $extra);
        $maxSteps = max(1, min(30, (int) config('ai.max_steps', 12)));

        for ($step = 0; $step < $maxSteps; ++$step) {
            $reply = $this->chatWithFallback($model, $this->history($conversation, $system), $tools, $usedModel);

            $toolCalls = array_map(fn (array $call) => array_filter([
                'id' => (string) ($call['id'] ?? ('call_' . Str::random(12))),
                'type' => 'function',
                'function' => [
                    'name' => (string) ($call['function']['name'] ?? ''),
                    'arguments' => is_string($call['function']['arguments'] ?? null) ? $call['function']['arguments'] : json_encode($call['function']['arguments'] ?? new \stdClass()),
                ],
                'thought_signature' => $call['thought_signature'] ?? null,
            ], fn ($value) => $value !== null), $reply['tool_calls']);

            AiMessage::query()->create([
                'conversation_id' => $conversation->id,
                'role' => 'assistant',
                'content' => $reply['content'],
                'tool_calls' => $toolCalls ?: null,
                'meta' => ['model' => $usedModel->model_id, 'provider' => $usedModel->provider->definition()['name'] ?? null],
            ]);

            if ($toolCalls === []) {
                return;
            }

            $waiting = false;
            foreach ($toolCalls as $call) {
                $waiting = $this->handleToolCall($conversation, $user, $server, $mode, $call) || $waiting;
            }

            if ($waiting) {
                return;
            }
        }

        AiMessage::query()->create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'I stopped here because this task took too many steps. Tell me to continue if you want me to keep going.',
        ]);
    }

    /**
     * @return bool true when the call is waiting for user approval
     */
    private function handleToolCall(AiConversation $conversation, ?User $user, ?Server $server, string $mode, array $call): bool
    {
        $name = $call['function']['name'];
        $args = json_decode($call['function']['arguments'] ?: '{}', true);

        if (!is_array($args)) {
            $this->storeToolResult($conversation, $call['id'], $name, 'Error: arguments were not valid JSON.');

            return false;
        }

        if (!$user) {
            $this->storeToolResult($conversation, $call['id'], $name, AiToolbox::isPublicTool($name)
                ? $this->toolbox->plansAndPricing($args)
                : 'Error: tools are unavailable for unlinked users.');

            return false;
        }

        if (AiToolbox::isWriteTool($name)) {
            if ($mode !== AiToolbox::MODE_AGENT || $conversation->channel !== 'panel') {
                $this->storeToolResult($conversation, $call['id'], $name, 'Error: changes are not allowed in Ask mode. Explain the steps, or tell the user to switch to Agent mode.');

                return false;
            }

            try {
                $target = $this->toolbox->resolveServer($user, $args['server'] ?? null, $server);
                $this->toolbox->assertAllowed($user, $target, $name, $args);
            } catch (Throwable $exception) {
                $this->storeToolResult($conversation, $call['id'], $name, 'Error: ' . $exception->getMessage());

                return false;
            }

            if ($name === 'write_file') {
                $args['_original'] = $this->originalContent($target, (string) $args['path']);
            }

            AiPendingAction::query()->create([
                'uuid' => (string) Str::uuid(),
                'conversation_id' => $conversation->id,
                'server_id' => $target->id,
                'tool_call_id' => $call['id'],
                'tool' => $name,
                'arguments' => $args,
                'status' => 'pending',
            ]);

            return true;
        }

        try {
            $result = $name === 'get_my_billing' && !$this->privateChannel
                ? 'Error: billing details are only shared in private chats. Ask the user to DM you or use the panel chat.'
                : $this->toolbox->runImmediate($user, $server, $name, $args, $conversation->channel);
        } catch (Throwable $exception) {
            $result = 'Error: ' . $exception->getMessage();
        }

        $this->storeToolResult($conversation, $call['id'], $name, $result);

        return false;
    }

    private function originalContent(Server $server, string $path): ?string
    {
        try {
            $content = $this->files->setServer($server)->getContent($path, self::ORIGINAL_PREVIEW_LIMIT);
        } catch (Throwable) {
            return null;
        }

        return mb_check_encoding($content, 'UTF-8') ? $content : null;
    }

    private function cleanArgs(array $args): array
    {
        unset($args['_original']);

        return $args;
    }

    private function storeToolResult(AiConversation $conversation, string $callId, string $name, string $result): void
    {
        AiMessage::query()->create([
            'conversation_id' => $conversation->id,
            'role' => 'tool',
            'tool_call_id' => $callId,
            'content' => mb_substr($result, 0, 120000),
            'meta' => ['tool' => $name],
        ]);
    }

    private function rejectOutstanding(AiConversation $conversation, string $reason): void
    {
        $pending = $conversation->pendingActions()->where('status', 'pending')->get();

        foreach ($pending as $action) {
            $action->update(['status' => 'rejected', 'result' => $reason]);
            $this->storeToolResult($conversation, $action->tool_call_id, $action->tool, $reason);
        }
    }

    private function history(AiConversation $conversation, string $system): array
    {
        $rows = AiMessage::query()
            ->where('conversation_id', $conversation->id)
            ->orderByDesc('id')
            ->limit(self::HISTORY_LIMIT)
            ->get()
            ->reverse()
            ->values();

        while ($rows->isNotEmpty() && $rows->first()->role === 'tool') {
            $rows->shift();
        }

        $messages = [['role' => 'system', 'content' => $system]];
        $previousMode = null;
        foreach ($rows as $row) {
            if ($row->role === 'tool') {
                $messages[] = ['role' => 'tool', 'tool_call_id' => $row->tool_call_id, 'content' => (string) $row->content];
                continue;
            }

            $message = ['role' => $row->role, 'content' => (string) ($row->content ?? '')];

            // Earlier turns may contain Ask-mode refusals; make a switch explicit so the model stops repeating them.
            $mode = $row->role === 'user' ? ($row->meta['mode'] ?? AiToolbox::MODE_ASK) : null;
            if ($mode && $previousMode && $mode !== $previousMode) {
                $message['content'] = ($mode === AiToolbox::MODE_AGENT
                    ? '[System note: the user switched to AGENT mode. Earlier "not allowed in Ask mode" answers no longer apply. You may now call write tools; each one is shown to the user as an Approve/Reject card, so call the tool instead of saying you cannot.]'
                    : '[System note: the user switched to ASK mode. Do not call write tools any more; explain steps instead.]') . "\n\n" . $message['content'];
            }
            if ($mode) {
                $previousMode = $mode;
            }
            if ($row->role === 'assistant' && !empty($row->tool_calls)) {
                $message['tool_calls'] = $row->tool_calls;
            }
            $messages[] = $message;
        }

        return $messages;
    }

    private function chatWithFallback(?AiModel $preferred, array $messages, array $tools, ?AiModel &$used = null): array
    {
        $preferred ??= AiModel::usable()->where('is_default', true)->first();
        $preferredId = $preferred?->model_id;

        // Order: chosen model, then the same model on other connected accounts,
        // then everything else by account priority and model priority.
        $chain = AiModel::usable()->get()->sortBy([
            fn ($a, $b) => ($b->id === $preferred?->id) <=> ($a->id === $preferred?->id),
            fn ($a, $b) => ($b->model_id === $preferredId) <=> ($a->model_id === $preferredId),
            fn ($a, $b) => $a->provider->priority <=> $b->provider->priority,
            fn ($a, $b) => $a->priority <=> $b->priority,
        ])->values();

        if ($chain->isEmpty()) {
            throw new DisplayException('No AI model is configured yet. An administrator needs to set one up in /admin/ai.');
        }

        $ready = $chain->reject(fn (AiModel $m) => Cache::has(self::COOLDOWN_KEY . $m->id));
        $candidates = ($ready->isNotEmpty() ? $ready : $chain)->take(self::MAX_FALLBACKS + 1);

        $errors = [];
        foreach ($candidates as $candidate) {
            try {
                $reply = $this->client->chat($candidate, $messages, $tools);
                $used = $candidate;

                return $reply;
            } catch (AiProviderException|DisplayException $exception) {
                $message = $exception->getMessage();
                $errors[] = $message;
                report($exception);

                if (preg_match('/HTTP (429|402|403)|quota|rate.?limit|exhausted|capacity|token refresh failed/i', $message)) {
                    Cache::put(self::COOLDOWN_KEY . $candidate->id, $message, now()->addMinutes(self::COOLDOWN_MINUTES));
                }
            }
        }

        throw new DisplayException('The AI provider is unavailable right now. ' . implode(' | ', $errors));
    }
}
