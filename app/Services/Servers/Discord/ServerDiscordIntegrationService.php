<?php

namespace Pterodactyl\Services\Servers\Discord;

use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Support\Str;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;
use Pterodactyl\Models\Discord\ServerDiscordAgent;
use Pterodactyl\Models\Discord\ServerDiscordIntegration;
use Pterodactyl\Models\Server;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;

class ServerDiscordIntegrationService
{
    private const AGENT_DIRECTORY = '/.burhan/.discord';
    private const AGENT_MANIFEST = self::AGENT_DIRECTORY . '/discord-agent.json';
    private const LEGACY_AGENT_MANIFEST = '/.pterodactyl/discord-agent.json';

    public function __construct(
        private Encrypter $encrypter,
        private DaemonFileRepository $fileRepository,
        private DiscordAgentDetectionService $detection,
    ) {
    }

    public function status(Server $server): array
    {
        $integration = $this->integration($server);
        $agent = $this->agent($server);
        $detected = $this->detection->detect($server);

        if (!$agent->detected_game_type || $agent->install_status === ServerDiscordAgent::INSTALL_NOT_INSTALLED) {
            $agent->forceFill([
                'detected_game_type' => $detected['detected_game_type'],
                'adapter' => $detected['adapter'],
                'detection_confidence' => $detected['confidence'],
                'detection_sources' => $detected['sources'],
                'capabilities' => $detected['capabilities'],
                'last_fingerprint' => $detected['fingerprint'],
            ])->save();
        }

        return $this->transform($server, $integration->refresh(), $agent->refresh());
    }

    public function update(Server $server, array $data): array
    {
        $integration = $this->integration($server);
        $payload = [
            'enabled' => (bool) ($data['enabled'] ?? $integration->enabled),
            'guild_id' => $this->nullableString($data['guild_id'] ?? $integration->guild_id),
            'chat_channel_id' => $this->nullableString($data['chat_channel_id'] ?? $integration->chat_channel_id),
            'console_channel_id' => $this->nullableString($data['console_channel_id'] ?? $integration->console_channel_id),
            'admin_channel_id' => $this->nullableString($data['admin_channel_id'] ?? $integration->admin_channel_id),
            'link_channel_id' => $this->nullableString($data['link_channel_id'] ?? $integration->link_channel_id),
            'chat_bridge_enabled' => (bool) ($data['chat_bridge_enabled'] ?? $integration->chat_bridge_enabled),
            'console_bridge_enabled' => (bool) ($data['console_bridge_enabled'] ?? $integration->console_bridge_enabled),
            'linking_enabled' => (bool) ($data['linking_enabled'] ?? $integration->linking_enabled),
            'whitelist_requires_link' => (bool) ($data['whitelist_requires_link'] ?? $integration->whitelist_requires_link),
            'features' => (array) ($data['features'] ?? $integration->features ?? []),
        ];

        if ($payload['enabled'] && !$integration->enabled) {
            $payload['enabled_at'] = now();
        }

        if (array_key_exists('bot_token', $data) && filled($data['bot_token'])) {
            $payload['bot_token_encrypted'] = $this->encrypter->encrypt(trim((string) $data['bot_token']));
        }

        $integration->forceFill($payload)->save();

        return $this->status($server);
    }

    /**
     * Writes the agent manifest into the server files. The runtime agent installer reads this file
     * after the next restart, handshakes with the panel, and begins feeding players/events.
     *
     * @throws DaemonConnectionException
     */
    public function install(Server $server): array
    {
        $integration = $this->integration($server);
        $agent = $this->agent($server);
        $detected = $this->detection->detect($server);
        $secret = Str::random(48);

        $agent->forceFill([
            'install_status' => ServerDiscordAgent::INSTALL_INSTALLED,
            'connection_status' => ServerDiscordAgent::CONNECTION_OFFLINE,
            'agent_secret_encrypted' => $this->encrypter->encrypt($secret),
            'detected_game_type' => $detected['detected_game_type'],
            'adapter' => $detected['adapter'],
            'detection_confidence' => $detected['confidence'],
            'detection_sources' => $detected['sources'],
            'capabilities' => $detected['capabilities'],
            'last_fingerprint' => $detected['fingerprint'],
            'installed_at' => now(),
            'restart_requested_at' => null,
            'last_sync_at' => null,
            'runtime_state' => null,
            'last_error' => null,
        ])->save();

        $manifest = [
            'version' => 1,
            'panel_url' => config('app.url'),
            'server_uuid' => $server->uuid,
            'server_identifier' => $server->uuidShort,
            'agent_secret' => $secret,
            'adapter' => $detected['adapter'],
            'detected_game_type' => $detected['detected_game_type'],
            'capabilities' => $detected['capabilities'],
            'discord' => [
                'enabled' => $integration->enabled,
                'guild_id' => $integration->guild_id,
                'chat_channel_id' => $integration->chat_channel_id,
                'console_channel_id' => $integration->console_channel_id,
                'admin_channel_id' => $integration->admin_channel_id,
                'link_channel_id' => $integration->link_channel_id,
                'chat_bridge_enabled' => $integration->chat_bridge_enabled,
                'console_bridge_enabled' => $integration->console_bridge_enabled,
                'linking_enabled' => $integration->linking_enabled,
                'whitelist_requires_link' => $integration->whitelist_requires_link,
            ],
        ];

        try {
            $this->fileRepository->setServer($server)->createDirectory('.burhan', '/');
        } catch (DaemonConnectionException) {
            // Directory may already exist; writing the manifest below is the authoritative install step.
        }

        try {
            $this->fileRepository->setServer($server)->createDirectory('.discord', '/.burhan');
        } catch (DaemonConnectionException) {
            // Directory may already exist; writing the manifest below is the authoritative install step.
        }

        $this->fileRepository->setServer($server)->putContent(
            self::AGENT_MANIFEST,
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        try {
            $this->fileRepository->setServer($server)->deleteFiles('/', [self::LEGACY_AGENT_MANIFEST]);
        } catch (DaemonConnectionException) {
            // Best effort cleanup only; the Burhan manifest above is authoritative.
        }

        return $this->status($server);
    }

    public function reset(Server $server): array
    {
        $agent = $this->agent($server);
        $agent->forceFill([
            'install_status' => ServerDiscordAgent::INSTALL_NOT_INSTALLED,
            'connection_status' => ServerDiscordAgent::CONNECTION_OFFLINE,
            'agent_secret_encrypted' => null,
            'last_error' => null,
            'installed_at' => null,
            'restart_requested_at' => null,
            'last_seen_at' => null,
            'last_sync_at' => null,
            'runtime_state' => null,
        ])->save();

        foreach ([self::AGENT_MANIFEST, self::LEGACY_AGENT_MANIFEST] as $manifest) {
            try {
                $this->fileRepository->setServer($server)->deleteFiles('/', [$manifest]);
            } catch (DaemonConnectionException) {
                // Best effort cleanup only.
            }
        }

        return $this->status($server);
    }

    private function integration(Server $server): ServerDiscordIntegration
    {
        return $server->discordIntegration()->firstOrCreate(['server_id' => $server->id]);
    }

    private function agent(Server $server): ServerDiscordAgent
    {
        return $server->discordAgent()->firstOrCreate(['server_id' => $server->id]);
    }

    private function transform(Server $server, ServerDiscordIntegration $integration, ServerDiscordAgent $agent): array
    {
        $connected = $agent->connection_status === ServerDiscordAgent::CONNECTION_CONNECTED
            && $agent->last_seen_at
            && $agent->last_seen_at->gt(now()->subSeconds(90));

        return [
            'integration' => [
                'enabled' => $integration->enabled,
                'has_bot_token' => $integration->hasBotToken(),
                'guild_id' => $integration->guild_id,
                'chat_channel_id' => $integration->chat_channel_id,
                'console_channel_id' => $integration->console_channel_id,
                'admin_channel_id' => $integration->admin_channel_id,
                'link_channel_id' => $integration->link_channel_id,
                'chat_bridge_enabled' => $integration->chat_bridge_enabled,
                'console_bridge_enabled' => $integration->console_bridge_enabled,
                'linking_enabled' => $integration->linking_enabled,
                'whitelist_requires_link' => $integration->whitelist_requires_link,
            ],
            'agent' => [
                'install_status' => $agent->install_status,
                'connection_status' => $connected ? ServerDiscordAgent::CONNECTION_CONNECTED : $agent->connection_status,
                'agent_version' => $agent->agent_version,
                'adapter' => $agent->adapter,
                'detected_game_type' => $agent->detected_game_type,
                'detection_confidence' => $agent->detection_confidence,
                'detection_sources' => $agent->detection_sources ?? [],
                'capabilities' => $agent->capabilities ?? [],
                'last_error' => $agent->last_error,
                'installed_at' => optional($agent->installed_at)->toAtomString(),
                'restart_requested_at' => optional($agent->restart_requested_at)->toAtomString(),
                'last_seen_at' => optional($agent->last_seen_at)->toAtomString(),
            ],
            'player_source' => [
                'mode' => $connected ? 'agent' : 'panel_fallback',
                'label' => $connected ? 'Synced by Discord Agent' : 'Using panel player provider fallback',
                'message' => $this->playerSourceMessage($integration, $agent, $connected),
            ],
            'server' => [
                'uuid' => $server->uuid,
                'identifier' => $server->uuidShort,
                'name' => $server->name,
            ],
        ];
    }

    private function playerSourceMessage(
        ServerDiscordIntegration $integration,
        ServerDiscordAgent $agent,
        bool $connected
    ): string {
        if ($connected) {
            return 'Agent connected. Online/offline players, linking, Discord chat, and supported actions use agent data.';
        }

        if ($agent->install_status === ServerDiscordAgent::INSTALL_NEEDS_RESTART) {
            return 'Agent installed. Restart the server to activate accurate Discord and player sync.';
        }

        if ($agent->install_status === ServerDiscordAgent::INSTALL_INSTALLED) {
            return 'Panel agent is installed. It will connect after the next scheduled sync, or immediately after pressing Sync Now.';
        }

        if (!$integration->enabled) {
            return 'Discord integration is disabled. Players are loaded using the existing panel provider.';
        }

        return 'Agent is not connected yet. Players are loaded using the existing panel provider until the agent handshakes.';
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
