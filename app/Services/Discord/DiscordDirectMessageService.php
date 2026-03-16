<?php

namespace Pterodactyl\Services\Discord;

use RuntimeException;
use Illuminate\Support\Arr;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\PendingRequest;

class DiscordDirectMessageService
{
    private const API_BASE = 'https://discord.com/api';

    public function isConfigured(): bool
    {
        return trim((string) config('services.discord.bot_token', '')) !== '';
    }

    public function sendToUser(string $discordUserId, string $content): void
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('Discord bot token is not configured.');
        }

        $channelResponse = $this->http()->post(self::API_BASE . '/users/@me/channels', [
            'recipient_id' => $discordUserId,
        ]);

        if (!$channelResponse->successful()) {
            throw new RuntimeException($this->discordErrorMessage($channelResponse, 'Unable to open a Discord DM channel.'));
        }

        $channelId = Arr::get($channelResponse->json(), 'id');
        if (!is_string($channelId) || trim($channelId) === '') {
            throw new RuntimeException('Discord did not return a DM channel identifier.');
        }

        $messageResponse = $this->http()->post(self::API_BASE . '/channels/' . $channelId . '/messages', [
            'content' => $content,
        ]);

        if (!$messageResponse->successful()) {
            throw new RuntimeException($this->discordErrorMessage($messageResponse, 'Unable to send the Discord DM.'));
        }
    }

    private function http(): PendingRequest
    {
        return Http::acceptJson()
            ->withToken(trim((string) config('services.discord.bot_token', '')), 'Bot')
            ->timeout((int) config('pterodactyl.guzzle.timeout', 30))
            ->connectTimeout((int) config('pterodactyl.guzzle.connect_timeout', 10));
    }

    private function discordErrorMessage(Response $response, string $fallback): string
    {
        $message = Arr::get($response->json(), 'message');
        if (is_string($message) && trim($message) !== '') {
            return $message;
        }

        return $fallback;
    }
}
