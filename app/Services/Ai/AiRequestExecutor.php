<?php

namespace Pterodactyl\Services\Ai;

use GuzzleHttp\Client;
use Illuminate\Support\Str;
use Pterodactyl\Models\Ai\AiModel;
use Pterodactyl\Models\Ai\AiProvider;
use GuzzleHttp\Exception\GuzzleException;
use Pterodactyl\Services\Ai\OAuth\AiOAuthService;

/**
 * Sends one chat turn to a connected provider and normalises the answer back
 * to OpenAI chat format. Request shapes, headers and quirks are ported from
 * 9router's open-sse executors/translators (MIT License, (c) decolua and contributors).
 *
 * Input: OpenAI chat messages + OpenAI function tools.
 * Output: ['content' => string, 'tool_calls' => OpenAI tool_calls[], 'usage' => ?array]
 */
class AiRequestExecutor
{
    public function __construct(private AiOAuthService $oauth)
    {
    }

    public function chat(AiModel $model, array $messages, array $tools): array
    {
        $provider = $model->provider;
        $definition = $provider->definition();
        if (!$definition) {
            throw new AiProviderException('This AI provider is not supported any more.');
        }

        $tools = ($model->supports_tools ?? true) ? $tools : [];
        $credentials = $this->oauth->credentials($provider);

        if (!in_array($definition['format'], ['gemini', 'gemini-cli', 'antigravity'], true)) {
            $messages = array_map(function (array $message) {
                if (!empty($message['tool_calls'])) {
                    $message['tool_calls'] = array_map(fn ($call) => array_diff_key($call, ['thought_signature' => true]), $message['tool_calls']);
                }

                return $message;
            }, $messages);
        }

        try {
            return $this->dispatch($definition, $provider, $model->model_id, $credentials, $messages, $tools);
        } catch (AiUnauthorizedException) {
            $credentials = $this->oauth->credentials($provider, true);

            return $this->dispatch($definition, $provider, $model->model_id, $credentials, $messages, $tools);
        }
    }

    private function dispatch(array $definition, AiProvider $provider, string $model, array $credentials, array $messages, array $tools): array
    {
        return match ($definition['format']) {
            'openai' => $this->openai($definition, $provider, $model, $credentials, $messages, $tools),
            'copilot' => $this->copilot($definition, $model, $credentials, $messages, $tools),
            'responses' => $this->responses($definition, $provider, $model, $credentials, $messages, $tools),
            'claude' => $this->claude($definition['base_url'] . '?beta=true', $this->kimiHeaders($definition, $credentials), $model, $messages, $tools, $definition['name']),
            'anthropic' => $this->claude($definition['base_url'], ($definition['headers'] ?? []) + [
                'x-api-key' => $credentials['api_key'] ?? '',
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ], $model, $messages, $tools, $definition['name']),
            'gemini' => $this->gemini($definition, $model, $credentials, $messages, $tools),
            'gemini-cli', 'antigravity' => $this->codeAssist($definition, $model, $credentials, $messages, $tools),
            'ollama' => $this->ollama($definition, $model, $credentials, $messages, $tools),
            default => throw new AiProviderException('Unsupported provider format: ' . $definition['format']),
        };
    }

    // ---------------------------------------------------------------- OpenAI

    private function openai(array $definition, AiProvider $provider, string $model, array $credentials, array $messages, array $tools): array
    {
        $token = $credentials['api_key'] ?? $credentials['access_token'] ?? '';
        $headers = ($definition['headers'] ?? []) + ['Content-Type' => 'application/json', 'Accept' => 'application/json'];
        $url = $definition['base_url'];

        switch ($provider->preset) {
            case 'cline':
                $token = preg_match('/^eyJ[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+/', $token) ? 'workos:' . $token : $token;
                $headers += [
                    'HTTP-Referer' => 'https://cline.bot', 'X-Title' => 'Cline', 'X-PLATFORM' => 'linux',
                    'X-CLIENT-TYPE' => '9router', 'X-IS-MULTIROOT' => 'false',
                ];
                break;
            case 'kilocode':
                if (!empty($credentials['org_id'])) {
                    $headers['X-Kilocode-OrganizationID'] = $credentials['org_id'];
                }
                break;
            case 'cloudflare-ai':
                $url = str_replace('{accountId}', rawurlencode((string) ($credentials['account_id'] ?? '')), $url);
                break;
            case 'openrouter':
                $headers += ['HTTP-Referer' => (string) config('app.url'), 'X-Title' => (string) config('app.name')];
                break;
        }

        $headers['Authorization'] = 'Bearer ' . $token;
        $payload = ['model' => $model, 'messages' => $messages, 'stream' => false];
        if ($tools) {
            $payload['tools'] = $tools;
            $payload['tool_choice'] = 'auto';
        }

        $body = $this->post($url, $headers, $payload, $definition['name']);
        if ($provider->preset === 'cline' && ($body['success'] ?? null) === true && is_array($body['data'] ?? null)) {
            $body = $body['data'];
        }

        return $this->fromOpenAi($body, $definition['name']);
    }

    private function fromOpenAi(array $body, string $name): array
    {
        $message = $body['choices'][0]['message'] ?? null;
        if (!is_array($message)) {
            throw new AiProviderException($name . ' returned an empty response.');
        }

        return [
            'content' => is_string($message['content'] ?? null) ? $message['content'] : '',
            'tool_calls' => array_values(array_filter($message['tool_calls'] ?? [], 'is_array')),
            'usage' => $body['usage'] ?? null,
        ];
    }

    // ---------------------------------------------------------------- GitHub Copilot

    private function copilot(array $definition, string $model, array $credentials, array $messages, array $tools): array
    {
        $headers = [
            'Authorization' => 'Bearer ' . ($credentials['copilot_token'] ?? $credentials['access_token']),
            'Content-Type' => 'application/json',
            'copilot-integration-id' => 'vscode-chat',
            'editor-version' => 'vscode/1.110.0',
            'editor-plugin-version' => 'copilot-chat/0.38.0',
            'user-agent' => 'GitHubCopilotChat/0.38.0',
            'openai-intent' => 'conversation-panel',
            'x-github-api-version' => '2025-04-01',
            'x-request-id' => (string) Str::uuid(),
            'x-vscode-user-agent-library-version' => 'electron-fetch',
            'X-Initiator' => 'user',
            'anthropic-version' => '2023-06-01',
            'Accept' => 'application/json',
        ];

        if (preg_match('/claude/i', $model)) {
            return $this->claude($definition['messages_url'], $headers, $model, $messages, $tools, 'GitHub Copilot');
        }

        if (preg_match('/codex/i', $model)) {
            return $this->responsesCall($definition['responses_url'], $headers, $this->toResponses($model, $messages, $tools, false), 'GitHub Copilot');
        }

        $payload = ['model' => $model, 'messages' => $messages, 'stream' => false];
        if ($tools) {
            $payload['tools'] = $tools;
        }

        return $this->fromOpenAi($this->post($definition['base_url'], $headers, $payload, 'GitHub Copilot'), 'GitHub Copilot');
    }

    // ---------------------------------------------------------------- OpenAI Responses (Codex, Grok CLI)

    private function responses(array $definition, AiProvider $provider, string $model, array $credentials, array $messages, array $tools): array
    {
        $session = 'anney-' . $provider->id;
        $headers = ($definition['headers'] ?? []) + [
            'Authorization' => 'Bearer ' . $credentials['access_token'],
            'Content-Type' => 'application/json',
            'Accept' => 'text/event-stream',
        ];

        if ($provider->preset === 'codex') {
            $headers['originator'] = 'codex_cli_rs';
            $headers['session_id'] = $session;
            if (!empty($credentials['chatgpt_account_id'])) {
                $headers['ChatGPT-Account-ID'] = $credentials['chatgpt_account_id'];
            }
            $payload = $this->toResponses($model, $messages, $tools, true);
            $payload['prompt_cache_key'] = $session;
            $payload['reasoning'] = ['effort' => 'low', 'summary' => 'auto'];
        } else {
            $headers += [
                'x-grok-client-identifier' => $definition['client_identifier'] ?? 'grok-shell',
                'x-grok-client-version' => $definition['client_version'] ?? '0.2.99',
                'x-grok-session-id' => $session,
                'x-grok-conv-id' => $session,
                'x-grok-req-id' => (string) Str::uuid(),
                'x-grok-turn-idx' => '1',
                'x-grok-model-override' => $model,
            ];
            if (!empty($credentials['email'])) {
                $headers['x-email'] = $credentials['email'];
            }
            if (!empty($credentials['user_id'])) {
                $headers['x-userid'] = $credentials['user_id'];
            }
            $payload = $this->toResponses($model, $messages, $tools, false);
        }

        $payload['stream'] = true;
        $payload['store'] = false;

        return $this->responsesCall($definition['base_url'], $headers, $payload, $definition['name']);
    }

    private function toResponses(string $model, array $messages, array $tools, bool $developerRole): array
    {
        $instructions = [];
        $input = [];

        foreach ($messages as $message) {
            $role = $message['role'];
            $text = $this->text($message['content'] ?? '');

            if ($role === 'system') {
                $instructions[] = $text;
                continue;
            }
            if ($role === 'tool') {
                $input[] = ['type' => 'function_call_output', 'call_id' => $message['tool_call_id'], 'output' => $text];
                continue;
            }
            if ($text !== '') {
                $input[] = [
                    'type' => 'message',
                    'role' => $role,
                    'content' => [['type' => $role === 'assistant' ? 'output_text' : 'input_text', 'text' => $text]],
                ];
            }
            foreach ($message['tool_calls'] ?? [] as $call) {
                $input[] = [
                    'type' => 'function_call',
                    'call_id' => $call['id'],
                    'name' => $call['function']['name'],
                    'arguments' => $call['function']['arguments'] ?: '{}',
                ];
            }
        }

        if ($input === []) {
            $input[] = ['type' => 'message', 'role' => 'user', 'content' => [['type' => 'input_text', 'text' => '...']]];
        }

        $payload = ['model' => $model, 'instructions' => implode("\n\n", $instructions), 'input' => $input];
        if (!$developerRole && $payload['instructions'] === '') {
            unset($payload['instructions']);
        }
        if ($tools) {
            $payload['tools'] = array_map(fn ($tool) => [
                'type' => 'function',
                'name' => $tool['function']['name'],
                'description' => $tool['function']['description'] ?? '',
                'parameters' => $tool['function']['parameters'],
            ], $tools);
            $payload['tool_choice'] = 'auto';
        }

        return $payload;
    }

    private function responsesCall(string $url, array $headers, array $payload, string $name): array
    {
        $raw = $this->postRaw($url, $headers, $payload, $name);
        $final = null;
        $text = '';
        $calls = [];

        if (str_contains($raw, 'data:')) {
            foreach (preg_split('/\r?\n/', $raw) as $line) {
                if (!str_starts_with($line, 'data:')) {
                    continue;
                }
                $event = json_decode(trim(substr($line, 5)), true);
                if (!is_array($event)) {
                    continue;
                }
                $type = $event['type'] ?? '';
                if ($type === 'response.output_text.delta') {
                    $text .= (string) ($event['delta'] ?? '');
                } elseif ($type === 'response.output_item.done' && ($event['item']['type'] ?? '') === 'function_call') {
                    $calls[] = $event['item'];
                } elseif (in_array($type, ['response.completed', 'response.done'], true)) {
                    $final = $event['response'] ?? null;
                } elseif (in_array($type, ['response.failed', 'error'], true)) {
                    $message = $event['response']['error']['message'] ?? $event['error']['message'] ?? $event['message'] ?? 'unknown error';
                    throw new AiProviderException($name . ': ' . $message);
                }
            }
        } else {
            $final = json_decode($raw, true);
        }

        if (is_array($final) && !empty($final['output'])) {
            $text = '';
            $calls = [];
            foreach ($final['output'] as $item) {
                if (($item['type'] ?? '') === 'message') {
                    foreach ($item['content'] ?? [] as $part) {
                        if (in_array($part['type'] ?? '', ['output_text', 'text'], true)) {
                            $text .= $part['text'] ?? '';
                        }
                    }
                } elseif (($item['type'] ?? '') === 'function_call') {
                    $calls[] = $item;
                }
            }
        }

        return [
            'content' => $text,
            'tool_calls' => array_map(fn ($item) => [
                'id' => (string) ($item['call_id'] ?? $item['id'] ?? ('call_' . Str::random(12))),
                'type' => 'function',
                'function' => ['name' => (string) $item['name'], 'arguments' => (string) ($item['arguments'] ?? '{}')],
            ], $calls),
            'usage' => $final['usage'] ?? null,
        ];
    }

    // ---------------------------------------------------------------- Anthropic Messages (Kimi, Copilot Claude)

    private function kimiHeaders(array $definition, array $credentials): array
    {
        return ($definition['headers'] ?? []) + $this->oauth->kimiRequestHeaders($credentials['device_id'] ?? null) + [
            'x-api-key' => $credentials['access_token'] ?? $credentials['api_key'] ?? '',
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    private function claude(string $url, array $headers, string $model, array $messages, array $tools, string $name): array
    {
        $system = [];
        $out = [];

        foreach ($messages as $message) {
            $role = $message['role'];
            if ($role === 'system') {
                $system[] = $this->text($message['content'] ?? '');
                continue;
            }

            if ($role === 'tool') {
                $block = ['type' => 'tool_result', 'tool_use_id' => $message['tool_call_id'], 'content' => $this->text($message['content'] ?? '')];
                $last = array_key_last($out);
                if ($last !== null && $out[$last]['role'] === 'user' && is_array($out[$last]['content']) && ($out[$last]['content'][0]['type'] ?? '') === 'tool_result') {
                    $out[$last]['content'][] = $block;
                } else {
                    $out[] = ['role' => 'user', 'content' => [$block]];
                }
                continue;
            }

            $blocks = [];
            $text = $this->text($message['content'] ?? '');
            if ($text !== '') {
                $blocks[] = ['type' => 'text', 'text' => $text];
            }
            foreach ($message['tool_calls'] ?? [] as $call) {
                $args = json_decode($call['function']['arguments'] ?: '{}', true);
                $blocks[] = ['type' => 'tool_use', 'id' => $call['id'], 'name' => $call['function']['name'], 'input' => is_array($args) && $args !== [] ? $args : new \stdClass()];
            }
            if ($blocks === []) {
                continue;
            }

            $last = array_key_last($out);
            if ($last !== null && $out[$last]['role'] === $role && $role === 'user') {
                $out[$last]['content'] = array_merge((array) $out[$last]['content'], $blocks);
            } else {
                $out[] = ['role' => $role, 'content' => $blocks];
            }
        }

        $payload = ['model' => $model, 'max_tokens' => 8192, 'messages' => $out];
        if ($system) {
            $payload['system'] = implode("\n\n", $system);
        }
        if ($tools) {
            $payload['tools'] = array_map(fn ($tool) => [
                'name' => $tool['function']['name'],
                'description' => $tool['function']['description'] ?? '',
                'input_schema' => $tool['function']['parameters'],
            ], $tools);
        }

        $body = $this->post($url, $headers, $payload, $name);
        $text = '';
        $calls = [];
        foreach ($body['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text') {
                $text .= $block['text'];
            } elseif (($block['type'] ?? '') === 'tool_use') {
                $calls[] = ['id' => $block['id'], 'type' => 'function', 'function' => ['name' => $block['name'], 'arguments' => json_encode($block['input'] ?: new \stdClass())]];
            }
        }

        return ['content' => $text, 'tool_calls' => $calls, 'usage' => $body['usage'] ?? null];
    }

    // ---------------------------------------------------------------- Gemini family

    private function toGemini(array $messages, array $tools): array
    {
        $system = [];
        $contents = [];
        $names = [];

        foreach ($messages as $message) {
            $role = $message['role'];
            if ($role === 'system') {
                $system[] = ['text' => $this->text($message['content'] ?? '')];
                continue;
            }

            if ($role === 'tool') {
                $part = ['functionResponse' => [
                    'name' => $names[$message['tool_call_id']] ?? 'tool',
                    'response' => ['result' => $this->text($message['content'] ?? '')],
                ]];
                $last = array_key_last($contents);
                if ($last !== null && $contents[$last]['role'] === 'user' && isset($contents[$last]['parts'][0]['functionResponse'])) {
                    $contents[$last]['parts'][] = $part;
                } else {
                    $contents[] = ['role' => 'user', 'parts' => [$part]];
                }
                continue;
            }

            $parts = [];
            $text = $this->text($message['content'] ?? '');
            if ($text !== '') {
                $parts[] = ['text' => $text];
            }
            foreach ($message['tool_calls'] ?? [] as $call) {
                $names[$call['id']] = $call['function']['name'];
                $args = json_decode($call['function']['arguments'] ?: '{}', true);
                // Gemini requires the signature it issued; the documented skip value covers calls made by other models.
                $parts[] = [
                    'functionCall' => ['name' => $call['function']['name'], 'args' => is_array($args) && $args !== [] ? $args : new \stdClass()],
                    'thoughtSignature' => $call['thought_signature'] ?? 'skip_thought_signature_validator',
                ];
            }
            if ($parts === []) {
                continue;
            }

            $geminiRole = $role === 'assistant' ? 'model' : 'user';
            $last = array_key_last($contents);
            if ($last !== null && $contents[$last]['role'] === $geminiRole && !isset($contents[$last]['parts'][0]['functionResponse'])) {
                $contents[$last]['parts'] = array_merge($contents[$last]['parts'], $parts);
            } else {
                $contents[] = ['role' => $geminiRole, 'parts' => $parts];
            }
        }

        $request = ['contents' => $contents, 'generationConfig' => ['temperature' => 0.4, 'maxOutputTokens' => 8192]];
        if ($system) {
            $request['systemInstruction'] = ['role' => 'user', 'parts' => $system];
        }
        if ($tools) {
            $request['tools'] = [['functionDeclarations' => array_map(fn ($tool) => [
                'name' => $tool['function']['name'],
                'description' => $tool['function']['description'] ?? '',
                'parameters' => $this->cleanSchema($tool['function']['parameters']),
            ], $tools)]];
        }

        return $request;
    }

    private function fromGemini(array $body): array
    {
        $candidate = $body['candidates'][0] ?? null;
        $text = '';
        $calls = [];

        foreach ($candidate['content']['parts'] ?? [] as $part) {
            if (isset($part['text']) && empty($part['thought'])) {
                $text .= $part['text'];
            }
            if (isset($part['functionCall'])) {
                $call = [
                    'id' => 'call_' . Str::random(16),
                    'type' => 'function',
                    'function' => ['name' => $part['functionCall']['name'], 'arguments' => json_encode($part['functionCall']['args'] ?? new \stdClass())],
                ];
                if (!empty($part['thoughtSignature'])) {
                    $call['thought_signature'] = $part['thoughtSignature'];
                }
                $calls[] = $call;
            }
        }

        return ['content' => $text, 'tool_calls' => $calls, 'usage' => $body['usageMetadata'] ?? null];
    }

    private function gemini(array $definition, string $model, array $credentials, array $messages, array $tools): array
    {
        $url = rtrim($definition['base_url'], '/') . '/' . rawurlencode($model) . ':generateContent';
        $body = $this->post($url, ['x-goog-api-key' => $credentials['api_key'] ?? '', 'Content-Type' => 'application/json'], $this->toGemini($messages, $tools), 'Gemini');

        return $this->fromGemini($body);
    }

    private function codeAssist(array $definition, string $model, array $credentials, array $messages, array $tools): array
    {
        $request = $this->toGemini($messages, $tools);
        $project = $credentials['project_id'] ?? '';

        if ($definition['format'] === 'antigravity') {
            $request['sessionId'] = (string) crc32('anney-' . ($credentials['account'] ?? 'x'));
            if ($tools) {
                $request['toolConfig'] = ['functionCallingConfig' => ['mode' => 'VALIDATED']];
            }
            $payload = [
                'project' => $project ?: 'anney-' . Str::lower(Str::random(5)),
                'model' => $model,
                'userAgent' => 'antigravity',
                'requestId' => 'agent/' . Str::uuid() . '/' . (int) (microtime(true) * 1000) . '/' . Str::uuid() . '/1',
                'request' => $request,
            ];
            $url = rtrim($definition['base_url'], '/') . '/v1internal:generateContent';
            $headers = ['User-Agent' => $definition['headers']['User-Agent'] ?? 'antigravity/ide/2.11.0 darwin/arm64'];
        } else {
            $payload = ['project' => $project, 'model' => $model, 'request' => $request];
            $url = rtrim($definition['base_url'], '/') . ':generateContent';
            $headers = [
                'User-Agent' => 'GeminiCLI/' . ($definition['cli_version'] ?? '0.34.0') . '/' . $model . ' (linux; x64; terminal)',
                'X-Goog-Api-Client' => $definition['api_client'] ?? 'google-genai-sdk/1.41.0 gl-node/v22.19.0',
            ];
        }

        $headers += ['Authorization' => 'Bearer ' . $credentials['access_token'], 'Content-Type' => 'application/json', 'Accept' => 'application/json'];
        $body = $this->post($url, $headers, $payload, $definition['name']);

        return $this->fromGemini($body['response'] ?? $body);
    }

    private function cleanSchema(mixed $schema): mixed
    {
        if (!is_array($schema)) {
            return $schema;
        }

        $allowed = ['type', 'description', 'properties', 'required', 'items', 'enum', 'format', 'nullable'];
        $clean = [];
        foreach ($schema as $key => $value) {
            if (is_int($key)) {
                $clean[$key] = $this->cleanSchema($value);
            } elseif ($key === 'properties') {
                $clean['properties'] = (object) array_map(fn ($p) => $this->cleanSchema($p), (array) $value);
            } elseif (in_array($key, $allowed, true)) {
                $clean[$key] = is_array($value) && $key !== 'required' && $key !== 'enum' ? $this->cleanSchema($value) : $value;
            }
        }

        if (($clean['type'] ?? null) === 'object' && empty((array) ($clean['properties'] ?? []))) {
            unset($clean['required']);
        }

        return $clean;
    }

    // ---------------------------------------------------------------- Ollama Cloud

    private function ollama(array $definition, string $model, array $credentials, array $messages, array $tools): array
    {
        $converted = array_map(function ($message) {
            $out = ['role' => $message['role'], 'content' => $this->text($message['content'] ?? '')];
            foreach ($message['tool_calls'] ?? [] as $call) {
                $out['tool_calls'][] = ['function' => ['name' => $call['function']['name'], 'arguments' => json_decode($call['function']['arguments'] ?: '{}', true) ?: new \stdClass()]];
            }

            return $out;
        }, $messages);

        $payload = ['model' => $model, 'messages' => $converted, 'stream' => false];
        if ($tools) {
            $payload['tools'] = $tools;
        }

        $body = $this->post($definition['base_url'], ['Authorization' => 'Bearer ' . ($credentials['api_key'] ?? ''), 'Content-Type' => 'application/json'], $payload, 'Ollama Cloud');
        $message = $body['message'] ?? [];

        return [
            'content' => (string) ($message['content'] ?? ''),
            'tool_calls' => array_map(fn ($call) => [
                'id' => 'call_' . Str::random(16),
                'type' => 'function',
                'function' => ['name' => $call['function']['name'], 'arguments' => json_encode($call['function']['arguments'] ?? new \stdClass())],
            ], $message['tool_calls'] ?? []),
            'usage' => null,
        ];
    }

    // ---------------------------------------------------------------- HTTP helpers

    private function text(mixed $content): string
    {
        if (is_string($content)) {
            return $content;
        }
        if (is_array($content)) {
            return implode('', array_map(fn ($part) => is_array($part) ? (string) ($part['text'] ?? '') : (string) $part, $content));
        }

        return '';
    }

    private function post(string $url, array $headers, array $payload, string $name): array
    {
        $raw = $this->postRaw($url, $headers, $payload, $name);
        $body = json_decode($raw, true);

        if (!is_array($body)) {
            throw new AiProviderException($name . ' returned an unreadable response: ' . mb_substr($raw, 0, 200));
        }

        return $body;
    }

    private function postRaw(string $url, array $headers, array $payload, string $name): string
    {
        try {
            $response = (new Client(['timeout' => 180, 'connect_timeout' => 10, 'http_errors' => false]))
                ->post($url, ['headers' => $headers, 'json' => $payload]);
        } catch (GuzzleException $exception) {
            throw new AiProviderException('Could not reach ' . $name . ': ' . $exception->getMessage());
        }

        $status = $response->getStatusCode();
        $raw = (string) $response->getBody();

        if ($status === 401) {
            throw new AiUnauthorizedException($name . ' rejected the login (401).');
        }
        if ($status >= 400) {
            $decoded = json_decode($raw, true);
            $detail = is_array($decoded) ? ($decoded['error']['message'] ?? $decoded['message'] ?? $decoded['error'] ?? $raw) : $raw;

            throw new AiProviderException(sprintf('%s returned HTTP %d: %s', $name, $status, mb_substr(is_string($detail) ? $detail : json_encode($detail), 0, 400)));
        }

        return $raw;
    }
}
