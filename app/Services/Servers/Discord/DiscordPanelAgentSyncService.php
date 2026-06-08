<?php

namespace Pterodactyl\Services\Servers\Discord;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;
use Pterodactyl\Models\Discord\ServerDiscordAgent;
use Pterodactyl\Models\Discord\ServerDiscordIntegration;
use Pterodactyl\Models\Discord\ServerPlayerLink;
use Pterodactyl\Models\Server;
use Pterodactyl\Repositories\Wings\DaemonCommandRepository;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;
use Pterodactyl\Services\Servers\Players\GameType;
use Throwable;

class DiscordPanelAgentSyncService
{
    private const API_BASE = 'https://discord.com/api/v10';
    private const MAX_DISCORD_LENGTH = 1900;
    private const MAX_CONSOLE_LINES_PER_SYNC = 35;

    public function __construct(
        private Encrypter $encrypter,
        private DaemonFileRepository $fileRepository,
        private DaemonCommandRepository $commandRepository,
    ) {
    }

    public function syncEnabled(?string $serverIdentifier = null): array
    {
        $query = Server::query()
            ->with(['discordIntegration', 'discordAgent'])
            ->whereHas('discordIntegration', fn ($query) => $query->where('enabled', true));

        if ($serverIdentifier) {
            $query->where(fn ($query) => $query
                ->where('uuidShort', $serverIdentifier)
                ->orWhere('uuid', $serverIdentifier)
                ->orWhere('uuid', 'like', "{$serverIdentifier}%")
            );
        }

        $results = [];
        $query->each(function (Server $server) use (&$results) {
            $results[$server->uuidShort] = $this->syncServer($server);
        });

        return $results;
    }

    public function syncServer(Server $server): array
    {
        $lock = Cache::lock(sprintf('discord-panel-agent-sync:%s', $server->uuid), 20);
        if (!$lock->get()) {
            return ['ok' => true, 'message' => 'Discord panel agent sync already running.'];
        }

        try {
            return $this->syncServerLocked($server);
        } finally {
            $lock->release();
        }
    }

    private function syncServerLocked(Server $server): array
    {
        $server->loadMissing(['discordIntegration', 'discordAgent']);
        $integration = $server->discordIntegration;
        $agent = $server->discordAgent;

        if (!$integration?->enabled) {
            return $this->markOffline($agent, 'Discord integration is disabled.');
        }

        if (!$agent || $agent->install_status === ServerDiscordAgent::INSTALL_NOT_INSTALLED) {
            return ['ok' => false, 'message' => 'Discord agent is not installed.'];
        }

        if ($integration->chat_bridge_enabled && !$integration->chat_channel_id) {
            return $this->markOffline($agent, 'Chat Channel ID is not configured.');
        }

        if ($integration->console_bridge_enabled && !$integration->console_channel_id) {
            return $this->markOffline($agent, 'Console Channel ID is not configured.');
        }

        if (!$integration->chat_bridge_enabled && !$integration->console_bridge_enabled) {
            return ['ok' => true, 'message' => 'Discord bridges are disabled.'];
        }

        try {
            $token = $this->decryptToken($integration);
        } catch (Throwable $exception) {
            return $this->markOffline($agent, $exception->getMessage());
        }

        $state = $agent->runtime_state ?? [];

        try {
            if ($integration->chat_bridge_enabled) {
                $this->syncGameChatToDiscord($server, $integration, $agent, $token, $state);
                $this->syncDiscordChatToGame($server, $integration, $token, $state);
            }

            if ($integration->console_bridge_enabled) {
                $this->syncConsoleLogToDiscord($server, $integration, $agent, $token, $state);
            }

            if ($integration->linking_enabled && $integration->link_channel_id) {
                $this->syncPlayerLinkCommands($server, $integration, $token, $state);
            }

            $agent->forceFill([
                'install_status' => ServerDiscordAgent::INSTALL_INSTALLED,
                'connection_status' => ServerDiscordAgent::CONNECTION_CONNECTED,
                'agent_version' => $agent->agent_version ?: 'panel-agent/1',
                'runtime_state' => $state,
                'last_error' => null,
                'last_seen_at' => now(),
                'last_sync_at' => now(),
            ])->save();

            return [
                'ok' => true,
                'message' => 'Discord panel agent synced.',
                'server' => $server->uuidShort,
                'last_seen_at' => $agent->last_seen_at?->toAtomString(),
            ];
        } catch (Throwable $exception) {
            return $this->markOffline($agent, $exception->getMessage());
        }
    }

    public function announcePowerEvent(Server $server, string $signal): void
    {
        $server->loadMissing(['discordIntegration', 'discordAgent']);
        $integration = $server->discordIntegration;
        $agent = $server->discordAgent;

        if (!$integration?->enabled || !$agent || $agent->install_status === ServerDiscordAgent::INSTALL_NOT_INSTALLED) {
            return;
        }

        $channelIds = array_values(array_unique(array_filter([
            $integration->admin_channel_id,
            $integration->console_channel_id,
            $integration->chat_channel_id,
        ])));

        if ($channelIds === []) {
            return;
        }

        try {
            $token = $this->decryptToken($integration);
            $message = $this->formatPowerMessage($server, $signal);
            foreach ($channelIds as $channelId) {
                $this->postDiscordMessage($token, (string) $channelId, $message);
            }

            $agent->forceFill([
                'connection_status' => ServerDiscordAgent::CONNECTION_CONNECTED,
                'last_error' => null,
                'last_seen_at' => now(),
                'last_sync_at' => now(),
            ])->save();
        } catch (Throwable $exception) {
            $this->markOffline($agent, sprintf('Power announcement failed: %s', $exception->getMessage()));
        }
    }

    private function syncGameChatToDiscord(
        Server $server,
        ServerDiscordIntegration $integration,
        ServerDiscordAgent $agent,
        string $token,
        array &$state
    ): void {
        if (($agent->detected_game_type ?: null) !== GameType::MINECRAFT_JAVA) {
            return;
        }

        try {
            $content = $this->fileRepository->setServer($server)->getContent('/logs/latest.log', 8 * 1024 * 1024);
        } catch (DaemonConnectionException) {
            return;
        }

        $lines = $this->splitLogLines($content);
        $lineCount = count($lines);
        $previousCount = (int) Arr::get($state, 'minecraft.latest_log.line_count', 0);
        $previousHash = (string) Arr::get($state, 'minecraft.latest_log.hash', '');
        $hash = sha1($content);

        if ($previousCount < 1 || $previousHash === '') {
            Arr::set($state, 'minecraft.latest_log.line_count', $lineCount);
            Arr::set($state, 'minecraft.latest_log.hash', $hash);

            return;
        }

        if ($lineCount < $previousCount && $previousHash === $hash && ($previousCount - $lineCount) <= 1) {
            Arr::set($state, 'minecraft.latest_log.line_count', $lineCount);
            Arr::set($state, 'minecraft.latest_log.hash', $hash);

            return;
        }

        if ($lineCount < $previousCount) {
            $previousCount = 0;
        }

        $newLines = array_slice($lines, $previousCount);
        foreach ($newLines as $line) {
            $this->syncMinecraftPlayerSnapshotFromLine($server, (string) $line);

            $message = $this->parseMinecraftChatLine((string) $line)
                ?: $this->parseMinecraftServerSayLine((string) $line)
                ?: $this->parseMinecraftLifecycleLine((string) $line);
            if (!$message) {
                continue;
            }

            $this->postDiscordChatBridgeMessage(
                $token,
                (string) $integration->chat_channel_id,
                $state,
                $message['player'],
                $message['message']
            );
        }

        Arr::set($state, 'minecraft.latest_log.line_count', $lineCount);
        Arr::set($state, 'minecraft.latest_log.hash', $hash);
    }

    private function syncDiscordChatToGame(
        Server $server,
        ServerDiscordIntegration $integration,
        string $token,
        array &$state
    ): void {
        $messages = $this->getDiscordMessages($token, (string) $integration->chat_channel_id);
        if ($messages === []) {
            return;
        }

        $lastSeen = (string) Arr::get($state, 'discord.chat.last_message_id', '');
        $latestId = (string) Arr::get($messages, '0.id', '');

        if ($lastSeen === '') {
            Arr::set($state, 'discord.chat.last_message_id', $latestId);

            return;
        }

        $pending = array_values(array_filter($messages, fn (array $message) => strcmp((string) $message['id'], $lastSeen) > 0));
        usort($pending, fn (array $a, array $b) => strcmp((string) $a['id'], (string) $b['id']));

        foreach ($pending as $message) {
            if ((bool) Arr::get($message, 'author.bot', false)) {
                continue;
            }

            $content = trim((string) Arr::get($message, 'content', ''));
            if ($content === '') {
                continue;
            }

            $author = trim((string) (Arr::get($message, 'member.nick') ?: Arr::get($message, 'author.global_name') ?: Arr::get($message, 'author.username') ?: 'Discord'));
            $command = $this->formatDiscordToMinecraftCommand($author, $content);
            $this->commandRepository->setServer($server)->send($command);
        }

        if ($latestId !== '') {
            Arr::set($state, 'discord.chat.last_message_id', $latestId);
        }
    }

    private function syncConsoleLogToDiscord(
        Server $server,
        ServerDiscordIntegration $integration,
        ServerDiscordAgent $agent,
        string $token,
        array &$state
    ): void {
        if (($agent->detected_game_type ?: null) !== GameType::MINECRAFT_JAVA) {
            return;
        }

        try {
            $content = $this->fileRepository->setServer($server)->getContent('/logs/latest.log', 8 * 1024 * 1024);
        } catch (DaemonConnectionException) {
            return;
        }

        $lines = $this->splitLogLines($content);
        $lineCount = count($lines);
        $previousCount = (int) Arr::get($state, 'minecraft.console_log.line_count', 0);
        $previousHash = (string) Arr::get($state, 'minecraft.console_log.hash', '');
        $hash = sha1($content);

        if ($previousCount < 1 || $previousHash === '') {
            Arr::set($state, 'minecraft.console_log.line_count', $lineCount);
            Arr::set($state, 'minecraft.console_log.hash', $hash);

            return;
        }

        if ($lineCount < $previousCount && $previousHash === $hash && ($previousCount - $lineCount) <= 1) {
            Arr::set($state, 'minecraft.console_log.line_count', $lineCount);
            Arr::set($state, 'minecraft.console_log.hash', $hash);

            return;
        }

        if ($lineCount < $previousCount) {
            $previousCount = 0;
        }

        $newLines = array_slice($lines, $previousCount);
        $consoleLines = array_values(array_filter(array_map(
            fn ($line) => $this->formatConsoleLine((string) $line),
            $newLines
        )));

        if ($consoleLines !== []) {
            $consoleLines = array_slice($consoleLines, -self::MAX_CONSOLE_LINES_PER_SYNC);
            $this->postDiscordMessage(
                $token,
                (string) $integration->console_channel_id,
                sprintf("**%s console**\n```log\n%s\n```", $server->name, Str::limit(implode("\n", $consoleLines), 1750, "\n..."))
            );
        }

        Arr::set($state, 'minecraft.console_log.line_count', $lineCount);
        Arr::set($state, 'minecraft.console_log.hash', $hash);
    }

    private function syncPlayerLinkCommands(
        Server $server,
        ServerDiscordIntegration $integration,
        string $token,
        array &$state
    ): void {
        $messages = $this->getDiscordMessages($token, (string) $integration->link_channel_id);
        if ($messages === []) {
            if (!Arr::get($state, 'discord.link.prompted_empty_channel')) {
                $this->postDiscordMessage(
                    $token,
                    (string) $integration->link_channel_id,
                    'Player linking is active. Send `/link YourMinecraftName` in this channel to link your Discord account.'
                );
                Arr::set($state, 'discord.link.prompted_empty_channel', true);
            }

            return;
        }

        $lastSeen = (string) Arr::get($state, 'discord.link.last_message_id', '');
        $latestId = (string) Arr::get($messages, '0.id', '');

        if ($lastSeen === '') {
            Arr::set($state, 'discord.link.last_message_id', $latestId);
            $this->postDiscordMessage(
                $token,
                (string) $integration->link_channel_id,
                'Player linking is active. Send `/link YourMinecraftName` in this channel to link your Discord account.'
            );

            return;
        }

        $pending = array_values(array_filter($messages, fn (array $message) => strcmp((string) $message['id'], $lastSeen) > 0));
        usort($pending, fn (array $a, array $b) => strcmp((string) $a['id'], (string) $b['id']));

        foreach ($pending as $message) {
            if ((bool) Arr::get($message, 'author.bot', false)) {
                continue;
            }

            $content = trim((string) Arr::get($message, 'content', ''));
            if (!preg_match('/^\/link\s+([A-Za-z0-9_]{3,16})\s*$/i', $content, $matches)) {
                continue;
            }

            $this->linkDiscordPlayer($server, $integration, $token, $message, $matches[1]);
        }

        if ($latestId !== '') {
            Arr::set($state, 'discord.link.last_message_id', $latestId);
        }
    }

    private function linkDiscordPlayer(
        Server $server,
        ServerDiscordIntegration $integration,
        string $token,
        array $message,
        string $playerName
    ): void {
        $discordUserId = (string) Arr::get($message, 'author.id', '');
        if ($discordUserId === '') {
            return;
        }

        $playerName = trim($playerName);
        $playerId = Str::lower($playerName);
        $discordUsername = (string) (Arr::get($message, 'member.nick') ?: Arr::get($message, 'author.global_name') ?: Arr::get($message, 'author.username') ?: 'Discord User');

        $snapshot = $server->playerSnapshots()
            ->whereRaw('LOWER(name) = ?', [$playerId])
            ->first();

        $existingPlayer = $server->playerLinks()
            ->where('player_id', $playerId)
            ->first();

        if ($existingPlayer && $existingPlayer->discord_user_id !== $discordUserId) {
            $this->postDiscordMessage(
                $token,
                (string) $integration->link_channel_id,
                sprintf('<@%s> `%s` is already linked to another Discord account.', $discordUserId, $playerName)
            );

            return;
        }

        $existingDiscord = $server->playerLinks()
            ->where('discord_user_id', $discordUserId)
            ->first();

        if ($existingDiscord && $existingDiscord->player_id !== $playerId && $existingPlayer) {
            $this->postDiscordMessage(
                $token,
                (string) $integration->link_channel_id,
                sprintf('<@%s> your Discord account is already linked and `%s` is unavailable.', $discordUserId, $playerName)
            );

            return;
        }

        /** @var ServerPlayerLink $link */
        $link = $existingDiscord ?: new ServerPlayerLink(['server_id' => $server->id]);
        $link->forceFill([
            'server_id' => $server->id,
            'player_id' => $playerId,
            'player_uuid' => $snapshot?->uuid,
            'player_name' => $snapshot?->name ?: $playerName,
            'discord_user_id' => $discordUserId,
            'discord_username' => $discordUsername,
            'link_code' => null,
            'status' => 'linked',
            'linked_at' => now(),
        ])->save();

        if ($snapshot) {
            $snapshot->forceFill(['discord_user_id' => $discordUserId])->save();
        } else {
            $server->playerSnapshots()->updateOrCreate(
                ['player_id' => $playerId],
                [
                    'uuid' => null,
                    'name' => $playerName,
                    'status' => 'offline',
                    'discord_user_id' => $discordUserId,
                    'first_seen_at' => now(),
                    'last_seen_at' => now(),
                    'meta' => ['source' => 'discord_link'],
                ]
            );
        }

        if ($integration->whitelist_requires_link) {
            $this->applyMinecraftWhitelist($server, $playerName);
        }

        $this->postDiscordMessage(
            $token,
            (string) $integration->link_channel_id,
            sprintf(
                '<@%s> linked to `%s`.%s',
                $discordUserId,
                $playerName,
                $integration->whitelist_requires_link ? ' Whitelist access has been updated.' : ''
            )
        );
    }

    private function applyMinecraftWhitelist(Server $server, string $playerName): void
    {
        $safeName = preg_replace('/[^A-Za-z0-9_]/', '', $playerName) ?: '';
        if ($safeName === '') {
            return;
        }

        $this->commandRepository->setServer($server)->send('whitelist on');
        $this->commandRepository->setServer($server)->send(sprintf('whitelist add %s', $safeName));
        $this->commandRepository->setServer($server)->send('whitelist reload');
    }

    private function syncMinecraftPlayerSnapshotFromLine(Server $server, string $line): void
    {
        $playerName = null;
        $status = null;

        if (preg_match('/\]: ([A-Za-z0-9_]{3,16}) joined the game$/', $line, $matches)) {
            $playerName = $matches[1];
            $status = 'online';
        } elseif (preg_match('/\]: ([A-Za-z0-9_]{3,16}) left the game$/', $line, $matches)) {
            $playerName = $matches[1];
            $status = 'offline';
        } elseif (preg_match('/\]: ([A-Za-z0-9_]{3,16}) lost connection: .+$/', $line, $matches)) {
            $playerName = $matches[1];
            $status = 'offline';
        } elseif (preg_match('/\]: (?:\[[^\]]+\] )?<([A-Za-z0-9_]{3,16})> .+$/', $line, $matches)) {
            $playerName = $matches[1];
            $status = 'online';
        }

        if (!$playerName || !$status) {
            return;
        }

        $playerId = Str::lower($playerName);
        $link = $server->playerLinks()->where('player_id', $playerId)->first();

        $existing = $server->playerSnapshots()->where('player_id', $playerId)->first();
        $payload = [
            'uuid' => $existing?->uuid,
            'name' => $playerName,
            'status' => $status,
            'discord_user_id' => $link?->discord_user_id ?: $existing?->discord_user_id,
            'last_seen_at' => now(),
            'meta' => array_filter([
                'source' => 'minecraft_log',
                'last_event' => $status,
            ]),
        ];

        if (!$existing) {
            $payload['first_seen_at'] = now();
        }

        $server->playerSnapshots()->updateOrCreate(['player_id' => $playerId], $payload);
    }

    private function decryptToken(ServerDiscordIntegration $integration): string
    {
        if (!$integration->bot_token_encrypted) {
            throw new \RuntimeException('Discord bot token is not configured.');
        }

        try {
            $token = trim((string) $this->encrypter->decrypt($integration->bot_token_encrypted));
        } catch (DecryptException) {
            throw new \RuntimeException('Discord bot token could not be decrypted.');
        }

        if ($token === '') {
            throw new \RuntimeException('Discord bot token is empty.');
        }

        return $token;
    }

    private function parseMinecraftChatLine(string $line): ?array
    {
        if (!preg_match('/\]: (?:\[[^\]]+\] )?<([^>]{1,32})> (.+)$/', $line, $matches)) {
            return null;
        }

        $player = trim($matches[1]);
        $message = trim($matches[2]);

        if ($player === '' || $message === '' || Str::startsWith($message, '[Discord]')) {
            return null;
        }

        return ['player' => $player, 'message' => $message];
    }

    private function splitLogLines(string $content): array
    {
        $lines = preg_split('/\R/', $content) ?: [];

        while ($lines !== [] && trim((string) end($lines)) === '') {
            array_pop($lines);
        }

        return $lines;
    }

    private function parseMinecraftServerSayLine(string $line): ?array
    {
        if (!preg_match('/\]: (?:\[[^\]]+\] )?\[Server\] (.+)$/', $line, $matches)) {
            return null;
        }

        $message = trim($matches[1]);
        if ($message === '' || Str::startsWith($message, '[Discord]')) {
            return null;
        }

        return ['player' => 'Server', 'message' => $message];
    }

    private function parseMinecraftLifecycleLine(string $line): ?array
    {
        if (preg_match('/\]: ([A-Za-z0-9_]{3,16}) joined the game$/', $line, $matches)) {
            return ['player' => $matches[1], 'message' => 'joined the game'];
        }

        if (preg_match('/\]: ([A-Za-z0-9_]{3,16}) left the game$/', $line, $matches)) {
            return ['player' => $matches[1], 'message' => 'left the game'];
        }

        if (preg_match('/\]: ([A-Za-z0-9_]{3,16}) lost connection: (.+)$/', $line, $matches)) {
            return ['player' => $matches[1], 'message' => sprintf('lost connection: %s', $this->cleanGameText($matches[2], 120))];
        }

        return null;
    }

    private function formatConsoleLine(string $line): ?string
    {
        $line = trim($line);
        if ($line === '') {
            return null;
        }

        if ($this->parseMinecraftChatLine($line)) {
            return null;
        }

        $line = preg_replace('/^\[[0-9:]+\]\s+\[[^]]+\]:\s*/', '', $line) ?: $line;
        $line = preg_replace('/\x1b\[[0-9;]*m/', '', $line) ?: $line;
        $line = $this->cleanGameText($line, 260);

        return $line === '' ? null : $line;
    }

    private function getDiscordMessages(string $token, string $channelId): array
    {
        $response = $this->discord($token)->get(sprintf('%s/channels/%s/messages', self::API_BASE, $channelId), [
            'limit' => 25,
        ]);

        $this->assertDiscordResponse($response, 'Unable to fetch Discord chat messages.');

        return $response->json() ?: [];
    }

    private function postDiscordMessage(string $token, string $channelId, string $content): void
    {
        $response = $this->discord($token)->post(sprintf('%s/channels/%s/messages', self::API_BASE, $channelId), [
            'content' => Str::limit($content, self::MAX_DISCORD_LENGTH, '...'),
            'allowed_mentions' => ['parse' => []],
        ]);

        $this->assertDiscordResponse($response, 'Unable to send Discord chat message.');
    }

    private function postDiscordChatBridgeMessage(
        string $token,
        string $channelId,
        array &$state,
        string $username,
        string $content
    ): void {
        $webhook = $this->chatWebhook($token, $channelId, $state);
        if ($webhook) {
            $response = Http::acceptJson()
                ->asJson()
                ->timeout(12)
                ->retry(1, 500)
                ->post(sprintf('%s/webhooks/%s/%s', self::API_BASE, $webhook['id'], $webhook['token']), [
                    'username' => $this->cleanDiscordUsername($username),
                    'content' => Str::limit($content, self::MAX_DISCORD_LENGTH, '...'),
                    'allowed_mentions' => ['parse' => []],
                ]);

            if ($response->successful()) {
                return;
            }
        }

        $this->postDiscordMessage($token, $channelId, sprintf('%s: %s', $this->cleanDiscordUsername($username), $content));
    }

    private function chatWebhook(string $token, string $channelId, array &$state): ?array
    {
        $cached = Arr::get($state, 'discord.chat.webhook');
        if (is_array($cached) && filled($cached['id'] ?? null) && filled($cached['token'] ?? null)) {
            return $cached;
        }

        try {
            $name = 'Pterodactyl Player Chat';
            $existing = $this->discord($token)->get(sprintf('%s/channels/%s/webhooks', self::API_BASE, $channelId));
            if ($existing->successful()) {
                foreach ($existing->json() ?: [] as $webhook) {
                    if (($webhook['name'] ?? '') === $name && filled($webhook['token'] ?? null)) {
                        $payload = ['id' => (string) $webhook['id'], 'token' => (string) $webhook['token']];
                        Arr::set($state, 'discord.chat.webhook', $payload);

                        return $payload;
                    }
                }
            }

            $created = $this->discord($token)->post(sprintf('%s/channels/%s/webhooks', self::API_BASE, $channelId), [
                'name' => $name,
            ]);

            if (!$created->successful() || !filled($created->json('token'))) {
                return null;
            }

            $payload = ['id' => (string) $created->json('id'), 'token' => (string) $created->json('token')];
            Arr::set($state, 'discord.chat.webhook', $payload);

            return $payload;
        } catch (Throwable) {
            return null;
        }
    }

    private function discord(string $token): \Illuminate\Http\Client\PendingRequest
    {
        $token = preg_replace('/^Bot\s+/i', '', trim($token)) ?: $token;

        return Http::withHeaders(['Authorization' => "Bot {$token}"])
            ->acceptJson()
            ->asJson()
            ->timeout(12)
            ->retry(1, 500);
    }

    private function assertDiscordResponse(Response $response, string $fallback): void
    {
        if ($response->successful()) {
            return;
        }

        $message = (string) ($response->json('message') ?: $fallback);
        throw new \RuntimeException(sprintf('%s Discord returned HTTP %s.', $message, $response->status()));
    }

    private function formatPowerMessage(Server $server, string $signal): string
    {
        $label = match (strtolower($signal)) {
            'start' => 'starting',
            'stop' => 'stopping',
            'restart' => 'restarting',
            'kill' => 'being killed',
            default => strtolower($signal),
        };

        return sprintf('**%s** is %s.', $server->name, $label);
    }

    private function formatDiscordToMinecraftCommand(string $author, string $content): string
    {
        $message = sprintf(
            '%s: %s',
            $this->cleanGameText($author, 32),
            $this->cleanGameText($content, 180)
        );

        return sprintf('tellraw @a %s', json_encode(['text' => $message], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private function cleanDiscordUsername(string $value): string
    {
        $value = trim(preg_replace('/[\r\n\t]+/', ' ', $value) ?: $value);

        return Str::limit($value === '' ? 'Player' : $value, 80, '');
    }

    private function cleanGameText(string $value, int $limit): string
    {
        $value = preg_replace('/[\r\n\t]+/', ' ', $value) ?: '';
        $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?: $value;

        return Str::limit(trim($value), $limit, '...');
    }

    private function markOffline(?ServerDiscordAgent $agent, string $message): array
    {
        if ($agent) {
            $agent->forceFill([
                'connection_status' => ServerDiscordAgent::CONNECTION_OFFLINE,
                'last_error' => Str::limit($message, 1000, '...'),
                'last_sync_at' => now(),
            ])->save();
        }

        return ['ok' => false, 'message' => $message];
    }
}
