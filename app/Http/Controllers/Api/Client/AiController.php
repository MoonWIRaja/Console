<?php

namespace Pterodactyl\Http\Controllers\Api\Client;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Models\Ai\AiModel;
use Pterodactyl\Models\Cloud\CloudFile;
use Pterodactyl\Models\Ai\AiMessage;
use Pterodactyl\Services\Ai\AiToolbox;
use Pterodactyl\Services\Ai\AiChatService;
use Pterodactyl\Models\Ai\AiConversation;
use Pterodactyl\Models\Ai\AiPendingAction;
use Pterodactyl\Exceptions\DisplayException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AiController extends ClientApiController
{
    public function __construct(private AiChatService $chat, private \Pterodactyl\Services\Cloud\CloudStorageService $storage)
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
        $conversation = $this->find($request, $conversation);
        $paths = $conversation->messages()->where('role', 'user')->get()
            ->flatMap(fn (AiMessage $m) => array_filter(array_map(fn ($f) => $f['media']['path'] ?? null, $m->meta['attachments'] ?? [])))->all();
        $conversation->delete();
        if ($paths !== []) {
            \Illuminate\Support\Facades\Storage::disk('local')->delete($paths);
        }

        return new JsonResponse([], 204);
    }

    public function message(Request $request, string $conversation): JsonResponse
    {
        $this->assertEnabled();
        $data = $request->validate([
            'content' => 'nullable|string',
            'mode' => 'required|in:ask,agent',
            'model' => 'nullable|integer',
            'files' => 'sometimes|array|max:10',
            'files.*' => 'file|max:' . (int) ($this->storage->maxUploadBytes() / 1024),
            'cloud_files' => 'sometimes|array|max:10',
            'cloud_files.*' => 'uuid',
            'failed_files' => 'sometimes|array|max:10',
            'failed_files.*.name' => 'required|string|max:255',
            'failed_files.*.size' => 'required|integer|min:0',
            'failed_files.*.error' => 'required|string|max:500',
        ]);

        if (blank($data['content'] ?? null) && empty($data['files']) && empty($data['cloud_files']) && empty($data['failed_files'])) {
            throw new DisplayException('Type a message or attach a file.');
        }

        $conversation = $this->find($request, $conversation);
        @set_time_limit(600);

        // Small files sent through the panel (used when Cloud Storage is off).
        $attachments = array_map(fn ($file) => $this->attach($request, $file), $request->file('files', []));

        // Files the browser already uploaded straight to the user's Cloud Storage.
        foreach ($data['cloud_files'] ?? [] as $uuid) {
            $file = CloudFile::query()->where('uuid', $uuid)->where('user_id', $request->user()->id)->first();
            if ($file) {
                $attachments[] = $this->attachStored($file);
            }
        }

        // Files that could not be saved (for example storage full); Anney tells the user.
        foreach ($data['failed_files'] ?? [] as $failed) {
            $attachments[] = [
                'name' => basename($failed['name']),
                'size' => (int) $failed['size'],
                'mime' => 'unknown',
                'error' => $failed['error'],
                'storage' => ['used' => $this->storage->usedBytes($request->user()), 'quota' => $this->storage->quotaBytes()],
            ];
        }

        $this->chat->send($conversation, $request->user(), (string) ($data['content'] ?? ''), $data['mode'], $this->model($data['model'] ?? null), [
            'attachments' => $attachments ?: null,
        ]);

        return new JsonResponse($this->full($conversation->fresh()));
    }

    private const TEXT_EXTENSIONS = ['txt', 'log', 'md', 'json', 'yml', 'yaml', 'toml', 'properties', 'cfg', 'conf', 'ini', 'xml', 'csv', 'js', 'ts', 'mjs', 'php', 'py', 'java', 'lua', 'sh', 'sql', 'html', 'css', 'env', 'mcmeta', 'snbt', 'json5', 'lang'];
    private const TEXT_LIMIT = 100 * 1024;

    /**
     * Reads a text attachment for the model, then keeps the file in the user's Cloud Storage.
     */
    private function isText(string $name, string $mime): bool
    {
        return in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), self::TEXT_EXTENSIONS, true) || str_starts_with($mime, 'text/');
    }

    /**
     * Reads the start of a text file already in Cloud Storage so Anney can see it.
     */
    private function attachStored(CloudFile $file): array
    {
        $attachment = ['name' => $file->name, 'size' => $file->size, 'mime' => $file->mime, 'uuid' => $file->uuid];
        if ($media = $this->keepMedia($file->name, (int) $file->size, fn () => $this->storage->stream($file)->getContents())) {
            $attachment['media'] = $media;
        }

        if ($this->isText($file->name, $file->mime)) {
            try {
                $stream = $this->storage->stream($file);
                $text = '';
                while (!$stream->eof() && strlen($text) <= self::TEXT_LIMIT) {
                    $text .= $stream->read(65536);
                }
                $stream->close();

                if (mb_check_encoding(mb_strcut($text, 0, self::TEXT_LIMIT, 'UTF-8'), 'UTF-8')) {
                    $attachment['truncated'] = strlen($text) > self::TEXT_LIMIT || $file->size > self::TEXT_LIMIT;
                    $attachment['text'] = mb_strcut($text, 0, self::TEXT_LIMIT, 'UTF-8');
                }
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        return $attachment;
    }

    private const MEDIA_LIMIT = 15 * 1024 * 1024;
    private const MEDIA_TYPES = [
        'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp', 'heic' => 'image/heic',
        'mp3' => 'audio/mp3', 'wav' => 'audio/wav', 'ogg' => 'audio/ogg', 'm4a' => 'audio/aac', 'aac' => 'audio/aac', 'flac' => 'audio/flac',
        'mp4' => 'video/mp4', 'webm' => 'video/webm', 'mov' => 'video/mov', 'pdf' => 'application/pdf',
    ];

    /**
     * Images, audio, video and PDFs are kept locally so the model can see or hear them on every turn.
     */
    private function keepMedia(string $name, int $size, callable $read): ?array
    {
        $mime = self::MEDIA_TYPES[strtolower(pathinfo($name, PATHINFO_EXTENSION))] ?? null;
        if (!$mime || $size <= 0 || $size > self::MEDIA_LIMIT) {
            return null;
        }
        $path = 'ai-media/' . \Illuminate\Support\Str::uuid() . '.bin';
        try {
            \Illuminate\Support\Facades\Storage::disk('local')->put($path, $read());
        } catch (\Throwable $exception) {
            report($exception);

            return null;
        }

        return ['path' => $path, 'mime' => $mime];
    }

    private function attach(Request $request, \Illuminate\Http\UploadedFile $file): array
    {
        $name = basename((string) $file->getClientOriginalName());
        $extension = strtolower($file->getClientOriginalExtension());
        $mime = (string) ($file->getMimeType() ?: 'application/octet-stream');
        $attachment = ['name' => $name, 'size' => (int) $file->getSize(), 'mime' => $mime];
        if ($media = $this->keepMedia($name, (int) $file->getSize(), fn () => file_get_contents($file->getRealPath()))) {
            $attachment['media'] = $media;
        }

        if (in_array($extension, self::TEXT_EXTENSIONS, true) || str_starts_with($mime, 'text/')) {
            $handle = fopen($file->getRealPath(), 'rb');
            $text = (string) fread($handle, self::TEXT_LIMIT + 1);
            fclose($handle);

            if (mb_check_encoding($text, 'UTF-8') || mb_check_encoding(substr($text, 0, -3), 'UTF-8')) {
                $attachment['truncated'] = strlen($text) > self::TEXT_LIMIT;
                $attachment['text'] = mb_strcut($text, 0, self::TEXT_LIMIT, 'UTF-8');
            }
        }

        if ($this->storage->enabled()) {
            try {
                $stored = $this->storage->upload($request->user(), $file, CloudFile::SOURCE_ANNEY);
                $attachment['uuid'] = $stored->uuid;
            } catch (DisplayException $exception) {
                $attachment['error'] = $exception->getMessage();
                $attachment['storage'] = ['used' => $this->storage->usedBytes($request->user()), 'quota' => $this->storage->quotaBytes()];
            }
        }

        return $attachment;
    }

    public function edit(Request $request, string $conversation, int $message): JsonResponse
    {
        $this->assertEnabled();
        $data = $request->validate([
            'content' => 'required|string',
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

    /**
     * Resumes a task that ran past one request's time budget.
     */
    public function continue(Request $request, string $conversation): JsonResponse
    {
        $this->assertEnabled();
        $data = $request->validate(['mode' => 'required|in:ask,agent', 'model' => 'nullable|integer']);
        $conversation = $this->find($request, $conversation);
        @set_time_limit(600);

        $this->chat->continueTask($conversation, $request->user(), $data['mode'], $this->model($data['model'] ?? null));

        return new JsonResponse($this->full($conversation->fresh()));
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
                'attachments' => array_map(fn (array $file) => [
                    'name' => $file['name'] ?? 'file',
                    'size' => (int) ($file['size'] ?? 0),
                    'download_url' => isset($file['uuid']) ? '/cloud/files/' . $file['uuid'] . '/download' : null,
                    'error' => isset($file['error']) ? 'Not saved: storage full or unavailable' : null,
                ], $message->meta['attachments'] ?? []),
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

            if ($item['content'] === '' && $item['tools'] === [] && $item['actions'] === [] && $item['attachments'] === []) {
                continue;
            }

            $items[] = $item;
        }

        return array_merge($this->summary($conversation), ['messages' => $items, 'continuing' => \Pterodactyl\Services\Ai\AiChatService::isContinuing($conversation)]);
    }
}
