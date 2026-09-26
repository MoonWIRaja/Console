<?php

namespace Pterodactyl\Services\Ai\Catalog;

/**
 * Provider definitions ported from 9router's provider registry
 * (https://github.com/decolua/9router, MIT License, (c) decolua and contributors).
 * Only the Subscription (OAuth) and Free groups are included.
 *
 * flow:   device | pkce_paste | google_paste | cline | kilocode | apikey
 * format: openai | copilot | responses | claude | gemini | gemini-cli | antigravity | ollama
 */
class ProviderCatalog
{
    public const RISK_NOTICE = 'Risk notice (from 9router): this provider uses a subscription/OAuth session not officially licensed for proxy/router use. The account may be restricted or banned. Use at your own risk.';

    /**
     * Official pay-as-you-go API keys that are shown even though the other
     * API-key providers are hidden. Based on 9router's registry/anthropic.js.
     */
    private const API_KEY_PROVIDERS = [
        'anthropic' => [
            'name' => 'Anthropic (Claude API)',
            'group' => 'apikey',
            'category' => 'apikey',
            'risk' => false,
            'flow' => 'apikey',
            'format' => 'anthropic',
            'base_url' => 'https://api.anthropic.com/v1/messages',
            'headers' => ['anthropic-version' => '2023-06-01'],
            'notice' => 'Official Claude API key from console.anthropic.com (pay-as-you-go). Not a Claude Pro/Max subscription.',
            'link' => 'https://console.anthropic.com/settings/keys',
            'oauth' => [],
            'models' => [],
            'models_fetcher' => ['url' => 'https://api.anthropic.com/v1/models?limit=100', 'type' => 'anthropic'],
        ],
    ];

    public static function all(): array
    {
        return array_filter(self::DEFINITIONS, fn (array $definition) => $definition['flow'] !== 'apikey') + self::API_KEY_PROVIDERS;
    }

    public static function get(string $id): ?array
    {
        return self::all()[$id] ?? null;
    }

    public static function grouped(): array
    {
        $groups = ['subscription' => [], 'free' => [], 'apikey' => []];
        foreach (self::all() as $id => $definition) {
            $groups[$definition['group']][$id] = $definition;
        }

        return $groups;
    }

    private const DEFINITIONS = 
[
        'github' => [
            'name' => 'GitHub Copilot',
            'group' => 'subscription',
            'category' => 'oauth',
            'risk' => true,
            'flow' => 'device',
            'format' => 'copilot',
            'base_url' => 'https://api.githubcopilot.com/chat/completions',
            'headers' => [
                'copilot-integration-id' => 'vscode-chat',
                'editor-version' => 'vscode/1.110.0',
                'editor-plugin-version' => 'copilot-chat/0.38.0',
                'user-agent' => 'GitHubCopilotChat/0.38.0',
                'openai-intent' => 'conversation-panel',
                'x-github-api-version' => '2025-04-01',
                'x-vscode-user-agent-library-version' => 'electron-fetch',
                'X-Initiator' => 'user',
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'notice' => null,
            'link' => 'https://github.com/features/copilot',
            'oauth' => [
                'clientId' => 'Iv1.b507a08c87ecfe98',
                'authorizeUrl' => 'https://github.com/login/oauth/authorize',
                'deviceCodeUrl' => 'https://github.com/login/device/code',
                'tokenUrl' => 'https://github.com/login/oauth/access_token',
                'userInfoUrl' => 'https://api.github.com/user',
                'scopes' => 'read:user',
                'apiVersion' => '2022-11-28',
                'copilotTokenUrl' => 'https://api.github.com/copilot_internal/v2/token',
                'userAgent' => 'GitHubCopilotChat/0.26.7',
                'editorVersion' => 'vscode/1.85.0',
                'editorPluginVersion' => 'copilot-chat/0.26.7',
            ],
            'models' => [
                [
                    'id' => 'gpt-5.2',
                    'name' => 'GPT-5.2',
                ],
                [
                    'id' => 'gpt-5.2-codex',
                    'name' => 'GPT-5.2 Codex',
                ],
                [
                    'id' => 'gpt-5.3-codex',
                    'name' => 'GPT-5.3 Codex',
                ],
                [
                    'id' => 'gpt-5.4',
                    'name' => 'GPT-5.4',
                ],
                [
                    'id' => 'gpt-5.4-mini',
                    'name' => 'GPT-5.4 Mini',
                ],
                [
                    'id' => 'claude-haiku-4.5',
                    'name' => 'Claude Haiku 4.5',
                ],
                [
                    'id' => 'claude-opus-4.5',
                    'name' => 'Claude Opus 4.5',
                ],
                [
                    'id' => 'claude-sonnet-4.5',
                    'name' => 'Claude Sonnet 4.5',
                ],
                [
                    'id' => 'claude-sonnet-4.6',
                    'name' => 'Claude Sonnet 4.6',
                ],
                [
                    'id' => 'claude-opus-4.6',
                    'name' => 'Claude Opus 4.6',
                ],
                [
                    'id' => 'claude-opus-4.7',
                    'name' => 'Claude Opus 4.7',
                ],
                [
                    'id' => 'gemini-2.5-pro',
                    'name' => 'Gemini 2.5 Pro',
                ],
                [
                    'id' => 'gemini-3-flash-preview',
                    'name' => 'Gemini 3 Flash',
                ],
                [
                    'id' => 'gemini-3.1-pro-preview',
                    'name' => 'Gemini 3.1 Pro',
                ],
                [
                    'id' => 'grok-code-fast-1',
                    'name' => 'Grok Code Fast 1',
                ],
                [
                    'id' => 'oswe-vscode-prime',
                    'name' => 'Raptor Mini',
                ],
                [
                    'id' => 'goldeneye-free-auto',
                    'name' => 'GoldenEye',
                ],
            ],
            'responses_url' => 'https://api.githubcopilot.com/responses',
            'messages_url' => 'https://api.githubcopilot.com/v1/messages',
        ],
        'codex' => [
            'name' => 'OpenAI Codex',
            'group' => 'subscription',
            'category' => 'oauth',
            'risk' => true,
            'flow' => 'pkce_paste',
            'format' => 'responses',
            'base_url' => 'https://chatgpt.com/backend-api/codex/responses',
            'headers' => [
                'originator' => 'codex_cli_rs',
                'User-Agent' => 'codex_cli_rs/0.154.0',
            ],
            'notice' => null,
            'link' => 'https://chatgpt.com/codex',
            'oauth' => [
                'clientId' => 'app_EMoamEEZ73f0CkXaXp7hrann',
                'authorizeUrl' => 'https://auth.openai.com/oauth/authorize',
                'tokenUrl' => 'https://auth.openai.com/oauth/token',
                'scope' => 'openid profile email offline_access',
                'codeChallengeMethod' => 'S256',
                'fixedPort' => 1455,
                'callbackPath' => '/auth/callback',
                'extraParams' => [
                    'id_token_add_organizations' => 'true',
                    'codex_cli_simplified_flow' => 'true',
                    'originator' => 'codex_cli_rs',
                ],
                'refreshLeadMs' => 432000000,
                'refresh' => [
                    'encoding' => 'form',
                    'scope' => 'openid profile email offline_access',
                ],
                'maxRefreshAgeMs' => 691200000,
                'trackRefreshAt' => true,
            ],
            'models' => [
                [
                    'id' => 'gpt-6-astra',
                    'name' => 'GPT 6.0 Astra',
                ],
                [
                    'id' => 'gpt-5.6-sol',
                    'name' => 'GPT 5.6 Sol',
                ],
                [
                    'id' => 'gpt-5.6-sol-review',
                    'name' => 'GPT 5.6 Sol Review',
                ],
                [
                    'id' => 'gpt-5.6-terra',
                    'name' => 'GPT 5.6 Terra',
                ],
                [
                    'id' => 'gpt-5.6-terra-review',
                    'name' => 'GPT 5.6 Terra Review',
                ],
                [
                    'id' => 'gpt-5.6-luna',
                    'name' => 'GPT 5.6 Luna',
                ],
                [
                    'id' => 'gpt-5.6-luna-review',
                    'name' => 'GPT 5.6 Luna Review',
                ],
                [
                    'id' => 'gpt-5.5',
                    'name' => 'GPT 5.5',
                ],
                [
                    'id' => 'gpt-5.5-review',
                    'name' => 'GPT 5.5 Review',
                ],
                [
                    'id' => 'gpt-5.4',
                    'name' => 'GPT 5.4',
                ],
                [
                    'id' => 'gpt-5.4-review',
                    'name' => 'GPT 5.4 Review',
                ],
                [
                    'id' => 'gpt-5.4-mini',
                    'name' => 'GPT 5.4 Mini',
                ],
                [
                    'id' => 'gpt-5.4-mini-review',
                    'name' => 'GPT 5.4 Mini Review',
                ],
                [
                    'id' => 'gpt-5.3-codex-spark',
                    'name' => 'GPT 5.3 Codex Spark',
                ],
                [
                    'id' => 'gpt-5.3-codex-spark-review',
                    'name' => 'GPT 5.3 Codex Spark Review',
                ],
                [
                    'id' => 'codex-auto-review',
                    'name' => 'Codex Auto Review',
                ],
            ],
            'cli_version' => '0.154.0',
        ],
        'grok-cli' => [
            'name' => 'Grok CLI (Grok Build)',
            'group' => 'subscription',
            'category' => 'oauth',
            'risk' => false,
            'flow' => 'device',
            'format' => 'responses',
            'base_url' => 'https://cli-chat-proxy.grok.com/v1/responses',
            'headers' => [
                'User-Agent' => 'grok-shell/0.2.99 (linux; x86_64)',
                'x-grok-client-identifier' => 'grok-shell',
                'x-grok-client-version' => '0.2.99',
            ],
            'notice' => 'Sign in with your xAI / Grok account via device code. Uses Grok Build subscription credits (cli-chat-proxy.grok.com).',
            'link' => 'https://grok.com/supergrok',
            'oauth' => [
                'clientId' => 'b1a00492-073a-47ea-816f-4c329264a828',
                'deviceCodeUrl' => 'https://auth.x.ai/oauth2/device/code',
                'tokenUrl' => 'https://auth.x.ai/oauth2/token',
                'refreshUrl' => 'https://auth.x.ai/oauth2/token',
                'scope' => 'openid profile email offline_access grok-cli:access api:access conversations:read conversations:write',
                'referrer' => 'grok-build',
                'refreshLeadMs' => 300000,
            ],
            'models' => [
                [
                    'id' => 'grok-build',
                    'name' => 'Grok Build',
                ],
                [
                    'id' => 'grok-4.5',
                    'name' => 'Grok 4.5',
                ],
                [
                    'id' => 'grok-4.5-high',
                    'name' => 'Grok 4.5 (High)',
                ],
                [
                    'id' => 'grok-4.5-medium',
                    'name' => 'Grok 4.5 (Medium)',
                ],
                [
                    'id' => 'grok-4.5-low',
                    'name' => 'Grok 4.5 (Low)',
                ],
            ],
            'models_list_url' => 'https://cli-chat-proxy.grok.com/v1/models',
            'client_version' => '0.2.99',
            'client_identifier' => 'grok-shell',
        ],
        'kimi' => [
            'name' => 'Kimi',
            'group' => 'subscription',
            'category' => 'oauth',
            'risk' => false,
            'flow' => 'device',
            'format' => 'claude',
            'base_url' => 'https://api.kimi.com/coding/v1/messages',
            'headers' => [
                'Anthropic-Version' => '2023-06-01',
                'Anthropic-Beta' => 'claude-code-20250219,interleaved-thinking-2025-05-14',
            ],
            'notice' => null,
            'link' => 'https://platform.moonshot.ai/console/api-keys',
            'oauth' => [
                'clientId' => '17e5f671-d194-4dfb-9706-5516cb48c098',
                'deviceCodeUrl' => 'https://auth.kimi.com/api/oauth/device_authorization',
                'tokenUrl' => 'https://auth.kimi.com/api/oauth/token',
                'refreshUrl' => 'https://auth.kimi.com/api/oauth/token',
                'refreshLeadMs' => 300000,
                'authorizeDeviceUrl' => 'https://www.kimi.com/code/authorize_device',
            ],
            'models' => [
                [
                    'id' => 'kimi-k3',
                    'name' => 'Kimi K3',
                ],
                [
                    'id' => 'k3',
                    'name' => 'Kimi K3 (Code)',
                ],
                [
                    'id' => 'kimi-for-coding',
                    'name' => 'Kimi for Coding',
                ],
                [
                    'id' => 'kimi-for-coding-highspeed',
                    'name' => 'Kimi for Coding Highspeed',
                ],
                [
                    'id' => 'kimi-k2.7-code',
                    'name' => 'Kimi K2.7 Code',
                ],
                [
                    'id' => 'kimi-k2.7-code-highspeed',
                    'name' => 'Kimi K2.7 Code Highspeed',
                ],
                [
                    'id' => 'kimi-k2.6',
                    'name' => 'Kimi K2.6',
                ],
                [
                    'id' => 'kimi-k2.5',
                    'name' => 'Kimi K2.5',
                ],
                [
                    'id' => 'kimi-k2.5-thinking',
                    'name' => 'Kimi K2.5 Thinking',
                ],
                [
                    'id' => 'kimi-latest',
                    'name' => 'Kimi Latest',
                ],
            ],
        ],
        'antigravity' => [
            'name' => 'Antigravity',
            'group' => 'subscription',
            'category' => 'oauth',
            'risk' => true,
            'flow' => 'google_paste',
            'format' => 'antigravity',
            'base_url' => 'https://daily-cloudcode-pa.googleapis.com',
            'headers' => [
                'User-Agent' => 'antigravity/ide/2.11.0 darwin/arm64',
            ],
            'notice' => null,
            'link' => 'https://antigravity.google',
            'oauth' => [
                'authorizeUrl' => 'https://accounts.google.com/o/oauth2/v2/auth',
                'tokenUrl' => 'https://oauth2.googleapis.com/token',
                'userInfoUrl' => 'https://www.googleapis.com/oauth2/v1/userinfo',
                'scopes' => [
                    'https://www.googleapis.com/auth/cloud-platform',
                    'https://www.googleapis.com/auth/userinfo.email',
                    'https://www.googleapis.com/auth/userinfo.profile',
                    'https://www.googleapis.com/auth/cclog',
                    'https://www.googleapis.com/auth/experimentsandconfigs',
                ],
                'apiEndpoint' => 'https://daily-cloudcode-pa.googleapis.com',
                'apiVersion' => 'v1internal',
                'loadCodeAssistEndpoint' => 'https://cloudcode-pa.googleapis.com/v1internal:loadCodeAssist',
                'onboardUserEndpoint' => 'https://cloudcode-pa.googleapis.com/v1internal:onboardUser',
                'loadCodeAssistUserAgent' => 'antigravity/ide/2.11.0 darwin/arm64',
                'refreshLeadMs' => 300000,
                'clientId' => '1071006060591-tmhssin2h21lcre235vtolojh4g403ep.apps.googleusercontent.com',
                'clientSecret' => '',
            ],
            'models' => [
                [
                    'id' => 'gemini-3.8-flash-high',
                    'name' => 'Gemini 3.8 Flash (High)',
                ],
                [
                    'id' => 'gemini-3.8-flash-medium',
                    'name' => 'Gemini 3.8 Flash (Medium)',
                ],
                [
                    'id' => 'gemini-3.8-flash-low',
                    'name' => 'Gemini 3.8 Flash (Low)',
                ],
                [
                    'id' => 'gemini-3.8-flash',
                    'name' => 'Gemini 3.8 Flash',
                ],
                [
                    'id' => 'gemini-3.7-flash-high',
                    'name' => 'Gemini 3.7 Flash (High)',
                ],
                [
                    'id' => 'gemini-3.7-flash-medium',
                    'name' => 'Gemini 3.7 Flash (Medium)',
                ],
                [
                    'id' => 'gemini-3.7-flash-low',
                    'name' => 'Gemini 3.7 Flash (Low)',
                ],
                [
                    'id' => 'gemini-3.6-flash-high',
                    'name' => 'Gemini 3.6 Flash (High)',
                ],
                [
                    'id' => 'gemini-3.6-flash-medium',
                    'name' => 'Gemini 3.6 Flash (Medium)',
                ],
                [
                    'id' => 'gemini-3.6-flash-low',
                    'name' => 'Gemini 3.6 Flash (Low)',
                ],
                [
                    'id' => 'gemini-3.5-flash-high',
                    'name' => 'Gemini 3.5 Flash (High)',
                ],
                [
                    'id' => 'gemini-3-flash-agent',
                    'name' => 'Gemini 3.5 Flash (High)',
                ],
                [
                    'id' => 'gemini-3.5-flash-low',
                    'name' => 'Gemini 3.5 Flash (Medium)',
                ],
                [
                    'id' => 'gemini-3.5-flash-extra-low',
                    'name' => 'Gemini 3.5 Flash (Low)',
                ],
                [
                    'id' => 'gemini-pro-agent',
                    'name' => 'Gemini 3.1 Pro (High)',
                ],
                [
                    'id' => 'gemini-3.1-pro-low',
                    'name' => 'Gemini 3.1 Pro (Low)',
                ],
                [
                    'id' => 'claude-sonnet-4-6',
                    'name' => 'Claude Sonnet 4.6 (Thinking)',
                ],
                [
                    'id' => 'claude-opus-4-6-thinking',
                    'name' => 'Claude Opus 4.6 (Thinking)',
                ],
                [
                    'id' => 'gpt-oss-120b-medium',
                    'name' => 'GPT-OSS 120B (Medium)',
                ],
                [
                    'id' => 'gemini-3-flash',
                    'name' => 'Gemini 3 Flash',
                ],
            ],
        ],
        'cline' => [
            'name' => 'Cline',
            'group' => 'subscription',
            'category' => 'oauth',
            'risk' => false,
            'flow' => 'cline',
            'format' => 'openai',
            'base_url' => 'https://api.cline.bot/api/v1/chat/completions',
            'headers' => [
                'HTTP-Referer' => 'https://cline.bot',
                'X-Title' => 'Cline',
            ],
            'notice' => null,
            'link' => 'https://cline.bot',
            'oauth' => [
                'appBaseUrl' => 'https://app.cline.bot',
                'apiBaseUrl' => 'https://api.cline.bot',
                'authorizeUrl' => 'https://api.cline.bot/api/v1/auth/authorize',
                'tokenExchangeUrl' => 'https://api.cline.bot/api/v1/auth/token',
                'refreshUrl' => 'https://api.cline.bot/api/v1/auth/refresh',
                'tokenUrl' => 'https://api.cline.bot/api/v1/auth/token',
            ],
            'models' => [
                [
                    'id' => 'anthropic/claude-opus-4.7',
                    'name' => 'Claude Opus 4.7',
                ],
                [
                    'id' => 'anthropic/claude-sonnet-4.6',
                    'name' => 'Claude Sonnet 4.6',
                ],
                [
                    'id' => 'anthropic/claude-opus-4.6',
                    'name' => 'Claude Opus 4.6',
                ],
                [
                    'id' => 'openai/gpt-5.3-codex',
                    'name' => 'GPT-5.3 Codex',
                ],
                [
                    'id' => 'openai/gpt-5.4',
                    'name' => 'GPT-5.4',
                ],
                [
                    'id' => 'google/gemini-3.1-pro-preview',
                    'name' => 'Gemini 3.1 Pro Preview',
                ],
                [
                    'id' => 'google/gemini-3.1-flash-lite-preview',
                    'name' => 'Gemini 3.1 Flash Lite Preview',
                ],
                [
                    'id' => 'kwaipilot/kat-coder-pro',
                    'name' => 'KAT Coder Pro',
                ],
            ],
        ],
        'kilocode' => [
            'name' => 'Kilo Code',
            'group' => 'subscription',
            'category' => 'oauth',
            'risk' => false,
            'flow' => 'kilocode',
            'format' => 'openai',
            'base_url' => 'https://api.kilo.ai/api/openrouter/chat/completions',
            'headers' => [],
            'notice' => null,
            'link' => 'https://kilocode.ai',
            'oauth' => [
                'apiBaseUrl' => 'https://api.kilo.ai',
                'initiateUrl' => 'https://api.kilo.ai/api/device-auth/codes',
                'pollUrlBase' => 'https://api.kilo.ai/api/device-auth/codes',
            ],
            'models' => [
                [
                    'id' => 'anthropic/claude-sonnet-4-20250514',
                    'name' => 'Claude Sonnet 4',
                ],
                [
                    'id' => 'anthropic/claude-opus-4-20250514',
                    'name' => 'Claude Opus 4',
                ],
                [
                    'id' => 'google/gemini-2.5-pro',
                    'name' => 'Gemini 2.5 Pro',
                ],
                [
                    'id' => 'google/gemini-2.5-flash',
                    'name' => 'Gemini 2.5 Flash',
                ],
                [
                    'id' => 'openai/gpt-4.1',
                    'name' => 'GPT-4.1',
                ],
                [
                    'id' => 'openai/o3',
                    'name' => 'o3',
                ],
                [
                    'id' => 'deepseek/deepseek-chat',
                    'name' => 'DeepSeek Chat',
                ],
                [
                    'id' => 'deepseek/deepseek-reasoner',
                    'name' => 'DeepSeek Reasoner',
                ],
            ],
            'models_fetcher' => [
                'url' => 'https://api.kilo.ai/api/gateway/models',
                'type' => 'openrouter-free',
            ],
        ],
        'xai' => [
            'name' => 'xAI (Grok)',
            'group' => 'subscription',
            'category' => 'oauth',
            'risk' => false,
            'flow' => 'pkce_paste',
            'format' => 'openai',
            'base_url' => 'https://api.x.ai/v1/chat/completions',
            'headers' => [],
            'notice' => null,
            'link' => 'https://console.x.ai',
            'oauth' => [
                'clientId' => 'b1a00492-073a-47ea-816f-4c329264a828',
                'tokenUrl' => 'https://auth.x.ai/oauth2/token',
                'refreshUrl' => 'https://auth.x.ai/oauth2/token',
            ],
            'models' => [
                [
                    'id' => 'grok-4.6',
                    'name' => 'Grok 4.6',
                ],
                [
                    'id' => 'grok-4.5',
                    'name' => 'Grok 4.5',
                ],
                [
                    'id' => 'grok-4',
                    'name' => 'Grok 4',
                ],
                [
                    'id' => 'grok-4-fast-reasoning',
                    'name' => 'Grok 4 Fast Reasoning',
                ],
                [
                    'id' => 'grok-code-fast-1',
                    'name' => 'Grok Code Fast',
                ],
                [
                    'id' => 'grok-3',
                    'name' => 'Grok 3',
                ],
            ],
            'models_url' => 'https://api.x.ai/v1/models',
            'responses_url' => 'https://api.x.ai/v1/responses',
        ],
        'gemini-cli' => [
            'name' => 'Gemini CLI',
            'group' => 'free',
            'category' => 'free',
            'risk' => true,
            'flow' => 'google_paste',
            'format' => 'gemini-cli',
            'base_url' => 'https://cloudcode-pa.googleapis.com/v1internal',
            'headers' => [],
            'notice' => null,
            'link' => 'https://github.com/google-gemini/gemini-cli',
            'oauth' => [
                'authorizeUrl' => 'https://accounts.google.com/o/oauth2/v2/auth',
                'tokenUrl' => 'https://oauth2.googleapis.com/token',
                'userInfoUrl' => 'https://www.googleapis.com/oauth2/v1/userinfo',
                'scopes' => [
                    'https://www.googleapis.com/auth/cloud-platform',
                    'https://www.googleapis.com/auth/userinfo.email',
                    'https://www.googleapis.com/auth/userinfo.profile',
                ],
                'refresh' => [
                    'encoding' => 'form',
                ],
                'clientId' => '681255809395-oo8ft2oprdrnp9e3aqf6av3hmdib135j.apps.googleusercontent.com',
                'clientSecret' => '',
            ],
            'models' => [
                [
                    'id' => 'gemini-3.1-pro-preview',
                    'name' => 'Gemini 3.1 Pro Preview',
                ],
                [
                    'id' => 'gemini-3-pro-preview',
                    'name' => 'Gemini 3 Pro Preview',
                ],
                [
                    'id' => 'gemini-3-flash-preview',
                    'name' => 'Gemini 3 Flash Preview',
                ],
                [
                    'id' => 'gemini-3.1-flash-lite-preview',
                    'name' => 'Gemini 3.1 Flash Lite Preview',
                ],
                [
                    'id' => 'gemini-2.5-pro',
                    'name' => 'Gemini 2.5 Pro',
                ],
                [
                    'id' => 'gemini-2.5-flash',
                    'name' => 'Gemini 2.5 Flash',
                ],
                [
                    'id' => 'gemini-2.5-flash-lite',
                    'name' => 'Gemini 2.5 Flash Lite',
                ],
            ],
            'cli_version' => '0.34.0',
            'api_client' => 'google-genai-sdk/1.41.0 gl-node/v22.19.0',
        ],
        'openrouter' => [
            'name' => 'OpenRouter',
            'group' => 'free',
            'category' => 'freeTier',
            'risk' => false,
            'flow' => 'apikey',
            'format' => 'openai',
            'base_url' => 'https://openrouter.ai/api/v1/chat/completions',
            'headers' => [
                'HTTP-Referer' => 'https://endpoint-proxy.local',
                'X-Title' => 'Endpoint Proxy',
            ],
            'notice' => 'Free tier: 27+ free models, no credit card needed, 200 req/day. After  0 credit: 1,000 req/day.',
            'link' => 'https://openrouter.ai/settings/keys',
            'oauth' => [],
            'models' => [
                [
                    'id' => 'perplexity/pplx-embed-v1-4b',
                    'name' => 'Perplexity Embed V1 4B',
                ],
                [
                    'id' => 'perplexity/pplx-embed-v1-0.6b',
                    'name' => 'Perplexity Embed V1 0.6B',
                ],
                [
                    'id' => 'nvidia/llama-nemotron-embed-vl-1b-v2:free',
                    'name' => 'NVIDIA Nemotron Embed VL 1B V2 (Free)',
                ],
                [
                    'id' => 'openai/gpt-4o-mini-tts',
                    'name' => 'GPT-4o Mini TTS',
                ],
                [
                    'id' => 'openai/tts-1-hd',
                    'name' => 'TTS-1 HD',
                ],
                [
                    'id' => 'openai/tts-1',
                    'name' => 'TTS-1',
                ],
                [
                    'id' => 'openai/dall-e-3',
                    'name' => 'DALL-E 3 (via OpenRouter)',
                ],
                [
                    'id' => 'black-forest-labs/FLUX.1-schnell',
                    'name' => 'FLUX.1 Schnell (via OpenRouter)',
                ],
                [
                    'id' => 'google/veo-3.1',
                    'name' => 'Veo 3.1 (via OpenRouter)',
                ],
                [
                    'id' => 'openai/sora-2-pro',
                    'name' => 'Sora 2 Pro (via OpenRouter)',
                ],
                [
                    'id' => 'bytedance/seedance-2.0',
                    'name' => 'Seedance 2.0 (via OpenRouter)',
                ],
                [
                    'id' => 'typesafe/jev-1.13',
                    'name' => 'Jev 1.13',
                ],
            ],
            'models_fetcher' => [
                'url' => 'https://openrouter.ai/api/v1/models',
                'type' => 'openrouter-free',
            ],
        ],
        'gemini' => [
            'name' => 'Gemini',
            'group' => 'free',
            'category' => 'freeTier',
            'risk' => false,
            'flow' => 'apikey',
            'format' => 'gemini',
            'base_url' => 'https://generativelanguage.googleapis.com/v1beta/models',
            'headers' => [],
            'notice' => null,
            'link' => 'https://aistudio.google.com/app/apikey',
            'oauth' => [
                'clientId' => '681255809395-oo8ft2oprdrnp9e3aqf6av3hmdib135j.apps.googleusercontent.com',
                'clientSecret' => '',
            ],
            'models' => [
                [
                    'id' => 'gemini-3.8-flash',
                    'name' => 'Gemini 3.8 Flash',
                ],
                [
                    'id' => 'gemini-3.7-flash',
                    'name' => 'Gemini 3.7 Flash',
                ],
                [
                    'id' => 'gemini-3.6-flash',
                    'name' => 'Gemini 3.6 Flash',
                ],
                [
                    'id' => 'gemini-3.5-flash-lite',
                    'name' => 'Gemini 3.5 Flash Lite',
                ],
                [
                    'id' => 'gemini-3.1-pro-preview',
                    'name' => 'Gemini 3.1 Pro Preview',
                ],
                [
                    'id' => 'gemini-3.1-flash-lite-preview',
                    'name' => 'Gemini 3.1 Flash Lite Preview',
                ],
                [
                    'id' => 'gemini-3-flash-preview',
                    'name' => 'Gemini 3 Flash Preview',
                ],
                [
                    'id' => 'gemini-2.5-pro',
                    'name' => 'Gemini 2.5 Pro',
                ],
                [
                    'id' => 'gemini-2.5-flash',
                    'name' => 'Gemini 2.5 Flash',
                ],
                [
                    'id' => 'gemini-2.5-flash-lite',
                    'name' => 'Gemini 2.5 Flash Lite',
                ],
                [
                    'id' => 'gemma-4-31b-it',
                    'name' => 'Gemma 4 31B IT',
                ],
                [
                    'id' => 'gemini-2.5-pro',
                    'name' => 'Gemini 2.5 Pro (Best)',
                ],
                [
                    'id' => 'gemini-2.5-flash',
                    'name' => 'Gemini 2.5 Flash',
                ],
                [
                    'id' => 'gemini-2.5-flash-lite',
                    'name' => 'Gemini 2.5 Flash Lite (Cheapest)',
                ],
                [
                    'id' => 'gemini-2.0-flash',
                    'name' => 'Gemini 2.0 Flash',
                ],
                [
                    'id' => 'gemini-3.1-flash-tts-preview',
                    'name' => 'Gemini 3.1 Flash TTS',
                ],
                [
                    'id' => 'gemini-2.5-flash-preview-tts',
                    'name' => 'Gemini 2.5 Flash TTS',
                ],
                [
                    'id' => 'gemini-2.5-pro-preview-tts',
                    'name' => 'Gemini 2.5 Pro TTS',
                ],
            ],
        ],
        'kilo-gateway' => [
            'name' => 'Kilo Gateway',
            'group' => 'free',
            'category' => 'freeTier',
            'risk' => false,
            'flow' => 'apikey',
            'format' => 'openai',
            'base_url' => 'https://api.kilo.ai/api/gateway/chat/completions',
            'headers' => [],
            'notice' => null,
            'link' => 'https://kilo.ai/dashboard?tab=apiKeys',
            'oauth' => [],
            'models' => [
                [
                    'id' => 'kilo-auto/free',
                    'name' => 'Kilo Auto Free',
                ],
                [
                    'id' => 'nvidia/nemotron-3-super-120b-a12b:free',
                    'name' => 'Nemotron 3 Super 120B (Free)',
                ],
                [
                    'id' => 'nvidia/nemotron-3-ultra-550b-a55b:free',
                    'name' => 'Nemotron 3 Ultra 550B (Free)',
                ],
                [
                    'id' => 'kwaipilot/kat-coder-pro-v2.5:free',
                    'name' => 'Kat Coder Pro v2.5 (Free)',
                ],
                [
                    'id' => 'kilo-auto/frontier',
                    'name' => 'Kilo Auto Frontier',
                ],
                [
                    'id' => 'kilo-auto/balanced',
                    'name' => 'Kilo Auto Balanced',
                ],
            ],
            'models_url' => 'https://api.kilo.ai/api/gateway/models',
        ],
        'nvidia' => [
            'name' => 'NVIDIA NIM',
            'group' => 'free',
            'category' => 'freeTier',
            'risk' => false,
            'flow' => 'apikey',
            'format' => 'openai',
            'base_url' => 'https://integrate.api.nvidia.com/v1/chat/completions',
            'headers' => [],
            'notice' => 'Free access for NVIDIA Developer Program members (prototyping & testing).',
            'link' => 'https://build.nvidia.com/settings/api-keys',
            'oauth' => [],
            'models' => [
                [
                    'id' => 'minimaxai/minimax-m2.7',
                    'name' => 'MiniMax M2.7',
                ],
                [
                    'id' => 'minimaxai/minimax-m3',
                    'name' => 'MiniMax M3',
                ],
                [
                    'id' => 'z-ai/glm-5.2',
                    'name' => 'GLM 5.2',
                ],
                [
                    'id' => 'deepseek-ai/deepseek-v4-pro',
                    'name' => 'DeepSeek V4 Pro',
                ],
                [
                    'id' => 'deepseek-ai/deepseek-v4-flash',
                    'name' => 'DeepSeek V4 Flash',
                ],
                [
                    'id' => 'moonshotai/kimi-k2.6',
                    'name' => 'Kimi K2.6',
                ],
                [
                    'id' => 'nvidia/nemotron-3-ultra-550b-a55b',
                    'name' => 'Nemotron 3 Ultra',
                ],
                [
                    'id' => 'nvidia/nv-embedqa-e5-v5',
                    'name' => 'NV EmbedQA E5 v5',
                ],
                [
                    'id' => 'nvidia/parakeet-ctc-1.1b-asr',
                    'name' => 'Parakeet CTC 1.1B',
                ],
                [
                    'id' => 'fastpitch',
                    'name' => 'FastPitch',
                ],
                [
                    'id' => 'tacotron2',
                    'name' => 'Tacotron2',
                ],
            ],
            'models_url' => 'https://integrate.api.nvidia.com/v1/models',
        ],
        'cloudflare-ai' => [
            'name' => 'Cloudflare',
            'group' => 'free',
            'category' => 'freeTier',
            'risk' => false,
            'flow' => 'apikey',
            'format' => 'openai',
            'base_url' => 'https://api.cloudflare.com/client/v4/accounts/{accountId}/ai/v1/chat/completions',
            'headers' => [],
            'notice' => 'Workers AI free tier. Requires a Cloudflare API token and Account ID.',
            'link' => 'https://dash.cloudflare.com/profile/api-tokens',
            'oauth' => [],
            'models' => [
                [
                    'id' => '@cf/meta/llama-3.2-1b-instruct',
                    'name' => 'Llama 3.2 1B Instruct',
                ],
                [
                    'id' => '@cf/meta/llama-3.2-3b-instruct',
                    'name' => 'Llama 3.2 3B Instruct',
                ],
                [
                    'id' => '@cf/meta/llama-3.1-8b-instruct-fp8-fast',
                    'name' => 'Llama 3.1 8B Instruct FP8 Fast',
                ],
                [
                    'id' => '@cf/meta/llama-3.1-8b-instruct-awq',
                    'name' => 'Llama 3.1 8B Instruct AWQ',
                ],
                [
                    'id' => '@cf/mistralai/mistral-small-3.1-24b-instruct',
                    'name' => 'Mistral Small 3.1 24B Instruct',
                ],
                [
                    'id' => '@cf/meta/llama-3.1-70b-instruct-fp8-fast',
                    'name' => 'Llama 3.1 70B Instruct FP8 Fast',
                ],
                [
                    'id' => '@cf/meta/llama-3.3-70b-instruct-fp8-fast',
                    'name' => 'Llama 3.3 70B Instruct FP8 Fast',
                ],
                [
                    'id' => '@cf/deepseek-ai/deepseek-r1-distill-qwen-32b',
                    'name' => 'DeepSeek R1 Distill Qwen 32B',
                ],
                [
                    'id' => '@cf/moonshotai/kimi-k2.5',
                    'name' => 'Kimi K2.5',
                ],
                [
                    'id' => '@cf/moonshotai/kimi-k2.6',
                    'name' => 'Kimi K2.6',
                ],
                [
                    'id' => '@cf/zai-org/glm-4.7-flash',
                    'name' => 'GLM 4.7 Flash',
                ],
                [
                    'id' => '@cf/qwen/qwq-32b',
                    'name' => 'QwQ 32B',
                ],
                [
                    'id' => '@cf/qwen/qwen2.5-coder-32b-instruct',
                    'name' => 'Qwen 2.5 Coder 32B Instruct',
                ],
                [
                    'id' => '@cf/black-forest-labs/flux-2-klein-9b',
                    'name' => 'FLUX.2 Klein 9B',
                ],
                [
                    'id' => '@cf/black-forest-labs/flux-2-klein-4b',
                    'name' => 'FLUX.2 Klein 4B',
                ],
                [
                    'id' => '@cf/black-forest-labs/flux-2-dev',
                    'name' => 'FLUX.2 Dev',
                ],
                [
                    'id' => '@cf/leonardo/lucid-origin',
                    'name' => 'Lucid Origin',
                ],
                [
                    'id' => '@cf/leonardo/phoenix-1.0',
                    'name' => 'Phoenix 1.0',
                ],
                [
                    'id' => '@cf/black-forest-labs/flux-1-schnell',
                    'name' => 'FLUX.1 Schnell',
                ],
                [
                    'id' => '@cf/bytedance/stable-diffusion-xl-lightning',
                    'name' => 'SDXL Lightning',
                ],
                [
                    'id' => '@cf/lykon/dreamshaper-8-lcm',
                    'name' => 'DreamShaper 8 LCM',
                ],
                [
                    'id' => '@cf/runwayml/stable-diffusion-v1-5-img2img',
                    'name' => 'Stable Diffusion v1.5 Img2Img',
                ],
                [
                    'id' => '@cf/runwayml/stable-diffusion-v1-5-inpainting',
                    'name' => 'Stable Diffusion v1.5 Inpainting',
                ],
                [
                    'id' => '@cf/stabilityai/stable-diffusion-xl-base-1.0',
                    'name' => 'SDXL Base 1.0',
                ],
            ],
        ],
        'bazaarlink' => [
            'name' => 'Bazaarlink',
            'group' => 'free',
            'category' => 'freeTier',
            'risk' => false,
            'flow' => 'apikey',
            'format' => 'openai',
            'base_url' => 'https://bazaarlink.ai/api/v1/chat/completions',
            'headers' => [],
            'notice' => null,
            'link' => 'https://bazaarlink.ai',
            'oauth' => [],
            'models' => [
                [
                    'id' => 'auto:free',
                    'name' => 'Auto Free (Zero Cost)',
                ],
                [
                    'id' => 'claude-opus-4.7',
                    'name' => 'Claude Opus 4.7',
                ],
                [
                    'id' => 'claude-sonnet-4.6',
                    'name' => 'Claude Sonnet 4.6',
                ],
                [
                    'id' => 'claude-haiku-4.5',
                    'name' => 'Claude Haiku 4.5',
                ],
                [
                    'id' => 'gpt-5.5',
                    'name' => 'GPT-5.5',
                ],
                [
                    'id' => 'gpt-5.4',
                    'name' => 'GPT-5.4',
                ],
                [
                    'id' => 'gpt-5.4-mini',
                    'name' => 'GPT-5.4 Mini',
                ],
                [
                    'id' => 'gpt-5.4-nano',
                    'name' => 'GPT-5.4 Nano',
                ],
                [
                    'id' => 'grok-4.3',
                    'name' => 'Grok 4.3',
                ],
                [
                    'id' => 'grok-4.20',
                    'name' => 'Grok 4.20',
                ],
                [
                    'id' => 'gemini-3.1-pro-preview',
                    'name' => 'Gemini 3.1 Pro',
                ],
                [
                    'id' => 'gemini-3-flash-preview',
                    'name' => 'Gemini 3 Flash',
                ],
                [
                    'id' => 'gemini-3.1-flash-lite-preview',
                    'name' => 'Gemini 3.1 Flash Lite',
                ],
                [
                    'id' => 'kimi-k2.6',
                    'name' => 'Kimi K2.6',
                ],
                [
                    'id' => 'kimi-k2.5',
                    'name' => 'Kimi K2.5',
                ],
                [
                    'id' => 'glm-5.1',
                    'name' => 'GLM 5.1',
                ],
                [
                    'id' => 'glm-5',
                    'name' => 'GLM 5',
                ],
                [
                    'id' => 'mimo-v2.5-pro',
                    'name' => 'MiMo-V2.5-Pro',
                ],
                [
                    'id' => 'mimo-v2.5',
                    'name' => 'MiMo-V2.5',
                ],
                [
                    'id' => 'minimax-m3',
                    'name' => 'MiniMax M3',
                ],
                [
                    'id' => 'minimax-m2.7',
                    'name' => 'MiniMax M2.7',
                ],
                [
                    'id' => 'minimax-m2.5',
                    'name' => 'MiniMax M2.5',
                ],
                [
                    'id' => 'qwen3.6-plus',
                    'name' => 'Qwen 3.6 Plus',
                ],
                [
                    'id' => 'nemotron-3-super-120b-a12b',
                    'name' => 'Nemotron 3 Super',
                ],
            ],
            'models_url' => 'https://bazaarlink.ai/api/v1/models',
        ],
        'api-airforce' => [
            'name' => 'API.airforce',
            'group' => 'free',
            'category' => 'freeTier',
            'risk' => false,
            'flow' => 'apikey',
            'format' => 'openai',
            'base_url' => 'https://api.airforce/v1/chat/completions',
            'headers' => [
                'HTTP-Referer' => 'https://endpoint-proxy.local',
                'X-Title' => 'Endpoint Proxy',
            ],
            'notice' => null,
            'link' => 'https://api.airforce',
            'oauth' => [],
            'models' => [
                [
                    'id' => 'gpt-oss-120b',
                    'name' => 'GPT-OSS 120B (Free)',
                ],
                [
                    'id' => 'gpt-oss-20b',
                    'name' => 'GPT-OSS 20B (Free)',
                ],
                [
                    'id' => 'kimi-k2.7-code',
                    'name' => 'Kimi K2.7 Code (Free)',
                ],
            ],
            'models_fetcher' => [
                'url' => 'https://api.airforce/v1/models',
                'type' => 'airforce-free',
            ],
            'models_url' => 'https://api.airforce/v1/models',
        ],
        'poolside' => [
            'name' => 'Poolside',
            'group' => 'free',
            'category' => 'freeTier',
            'risk' => false,
            'flow' => 'apikey',
            'format' => 'openai',
            'base_url' => 'https://inference.poolside.ai/v1/chat/completions',
            'headers' => [],
            'notice' => null,
            'link' => 'https://platform.poolside.ai/api-keys',
            'oauth' => [],
            'models' => [
                [
                    'id' => 'poolside/laguna-s-2.1',
                    'name' => 'Laguna S 2.1',
                ],
                [
                    'id' => 'poolside/laguna-xs-2.1',
                    'name' => 'Laguna XS 2.1',
                ],
            ],
            'models_url' => 'https://inference.poolside.ai/v1/models',
        ],
        'byteplus' => [
            'name' => 'BytePlus ModelArk',
            'group' => 'free',
            'category' => 'freeTier',
            'risk' => false,
            'flow' => 'apikey',
            'format' => 'openai',
            'base_url' => 'https://ark.ap-southeast.bytepluses.com/api/coding/v3/chat/completions',
            'headers' => [],
            'notice' => 'Free credits for new accounts. Access to Seed 2.0, Kimi K2 Thinking, GLM 4.7, GPT-OSS-120B models.',
            'link' => 'https://console.byteplus.com/ark/region:ark+ap-southeast-1/apiKey',
            'oauth' => [],
            'models' => [
                [
                    'id' => 'seed-2-0-pro-260328',
                    'name' => 'Seed 2.0 Pro',
                ],
                [
                    'id' => 'seed-2-0-code-preview-260328',
                    'name' => 'Seed 2.0 Code Preview',
                ],
                [
                    'id' => 'seed-2-0-mini-260215',
                    'name' => 'Seed 2.0 Mini',
                ],
                [
                    'id' => 'seed-2-0-lite-260228',
                    'name' => 'Seed 2.0 Lite',
                ],
                [
                    'id' => 'kimi-k2-thinking-251104',
                    'name' => 'Kimi K2 Thinking',
                ],
                [
                    'id' => 'glm-4-7-251222',
                    'name' => 'GLM 4.7',
                ],
                [
                    'id' => 'gpt-oss-120b-250805',
                    'name' => 'GPT-OSS-120B',
                ],
            ],
        ],
        'ollama' => [
            'name' => 'Ollama Cloud',
            'group' => 'free',
            'category' => 'freeTier',
            'risk' => false,
            'flow' => 'apikey',
            'format' => 'ollama',
            'base_url' => 'https://ollama.com/api/chat',
            'headers' => [],
            'notice' => 'Free tier: light usage, 1 cloud model at a time (limits reset every 5h & 7d). Pro $20/mo · Max $100/mo.',
            'link' => 'https://ollama.com/settings/keys',
            'oauth' => [],
            'models' => [
                [
                    'id' => 'gpt-oss:120b',
                    'name' => 'GPT OSS 120B',
                ],
                [
                    'id' => 'kimi-k2.5',
                    'name' => 'Kimi K2.5',
                ],
                [
                    'id' => 'glm-5',
                    'name' => 'GLM 5',
                ],
                [
                    'id' => 'minimax-m2.5',
                    'name' => 'MiniMax M2.5',
                ],
                [
                    'id' => 'glm-4.7-flash',
                    'name' => 'GLM 4.7 Flash',
                ],
                [
                    'id' => 'qwen3.5',
                    'name' => 'Qwen3.5',
                ],
                [
                    'id' => 'minimax-m3',
                    'name' => 'MiniMax M3',
                ],
                [
                    'id' => 'deepseek-v4.1-flash:cloud',
                    'name' => 'DeepSeek V4.1 Flash',
                ],
            ],
            'models_url' => 'https://ollama.com/api/tags',
        ],
    ];
}
