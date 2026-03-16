<?php

namespace Pterodactyl\Services\Tickets;

use RuntimeException;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Client\PendingRequest;
use Pterodactyl\Models\Ticket;
use Pterodactyl\Models\TicketMessage;
use Pterodactyl\Models\UserOAuthAccount;

class TicketDiscordService
{
    private const API_BASE = 'https://discord.com/api/v10';

    public function __construct(
        private TicketSettingsService $settings,
        private TicketUrlService $urls,
    ) {
    }

    public function ensureTicketThread(Ticket $ticket): Ticket
    {
        if ($ticket->discord_thread_id) {
            if ($this->threadIsAccessible($ticket->discord_thread_id)) {
                return $ticket;
            }

            $ticket->forceFill([
                'discord_thread_id' => null,
                'discord_sync_status' => Ticket::DISCORD_SYNC_FAILED,
                'discord_last_error' => 'The previous Discord thread is no longer available. A new thread will be created automatically.',
            ])->saveOrFail();
        }

        if (!$this->settings->enabled() || !$this->settings->hasBotToken() || !$this->settings->hasDiscordThreadConfig()) {
            $ticket->forceFill([
                'discord_sync_status' => Ticket::DISCORD_SYNC_FAILED,
                'discord_last_error' => 'Discord ticketing is not fully configured yet.',
            ])->saveOrFail();

            throw new RuntimeException('Discord ticketing is not fully configured yet.');
        }

        $response = $this->botHttp()->post(
            sprintf('%s/channels/%s/threads', self::API_BASE, $this->settings->activeParentChannelId()),
            [
                'name' => $this->threadName($ticket),
                'auto_archive_duration' => 1440,
                'type' => 12,
                'invitable' => false,
            ]
        );

        if (!$response->successful()) {
            $ticket->forceFill([
                'discord_sync_status' => Ticket::DISCORD_SYNC_FAILED,
                'discord_last_error' => $this->discordErrorMessage($response, 'Unable to create a Discord ticket thread.'),
            ])->saveOrFail();

            throw new RuntimeException($ticket->discord_last_error ?: 'Unable to create a Discord ticket thread.');
        }

        $threadId = Arr::get($response->json(), 'id');
        if (!is_string($threadId) || $threadId === '') {
            throw new RuntimeException('Discord did not return a valid thread identifier.');
        }

        $memberSyncWarnings = $this->syncInitialThreadMembers($ticket, $threadId);
        if ($memberSyncWarnings !== []) {
            $this->logAudit(sprintf(
                'Ticket %s Discord thread member sync warnings: %s',
                $ticket->ticket_number,
                implode(' ', $memberSyncWarnings)
            ));
        }

        $ticket->forceFill([
            'discord_thread_id' => $threadId,
            'discord_parent_channel_id' => $this->settings->activeParentChannelId(),
            'discord_sync_status' => Ticket::DISCORD_SYNC_SYNCED,
            'discord_last_synced_at' => now(),
            'discord_last_error' => $memberSyncWarnings !== [] ? implode(' ', $memberSyncWarnings) : null,
        ])->saveOrFail();

        $this->postThreadIntro($ticket->fresh());

        return $ticket->fresh();
    }

    public function relayMessage(TicketMessage $message): TicketMessage
    {
        $message->loadMissing('ticket', 'attachments', 'author');
        $ticket = $message->ticket?->fresh();
        if ($ticket) {
            $ticket = $this->ensureTicketThread($ticket);
        }

        if (!$ticket?->discord_thread_id || !$this->settings->hasRelayWebhook()) {
            $message->forceFill([
                'discord_sync_status' => TicketMessage::DISCORD_SYNC_SKIPPED,
                'discord_sync_error' => !$ticket?->discord_thread_id
                    ? 'Ticket thread is not linked to Discord.'
                    : 'Discord relay webhook is not configured.',
            ])->saveOrFail();

            return $message->fresh(['attachments']);
        }

        $payload = array_filter([
            'content' => $this->discordContent($message->body),
            'username' => $this->discordUsername($message),
            'avatar_url' => $this->discordAvatarUrl($message),
            'allowed_mentions' => ['parse' => []],
        ], fn ($value) => !is_null($value));

        $request = $this->webhookMultipartHttp();
        $attached = false;

        foreach ($message->attachments as $index => $attachment) {
            $disk = Storage::disk($attachment->disk);
            if (!$disk->exists($attachment->path)) {
                continue;
            }

            $request = $request->attach(
                sprintf('files[%d]', $index),
                $disk->get($attachment->path),
                $attachment->original_name,
                ['Content-Type' => $attachment->mime_type ?: 'application/octet-stream']
            );
            $attached = true;
        }

        $response = $attached
            ? $request->post($this->webhookUrl($ticket->discord_thread_id), [
                'payload_json' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ])
            : Http::acceptJson()
                ->timeout((int) config('pterodactyl.guzzle.timeout', 30))
                ->connectTimeout((int) config('pterodactyl.guzzle.connect_timeout', 10))
                ->post($this->webhookUrl($ticket->discord_thread_id), $payload);

        if (
            !$response->successful()
            && str_contains(strtolower($this->discordErrorMessage($response, '')), 'unknown channel')
        ) {
            $ticket = $this->ensureTicketThread($ticket->fresh());

            $response = $attached
                ? $request->post($this->webhookUrl($ticket->discord_thread_id), [
                    'payload_json' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                ])
                : Http::acceptJson()
                    ->timeout((int) config('pterodactyl.guzzle.timeout', 30))
                    ->connectTimeout((int) config('pterodactyl.guzzle.connect_timeout', 10))
                    ->post($this->webhookUrl($ticket->discord_thread_id), $payload);
        }

        if (!$response->successful()) {
            $message->forceFill([
                'discord_sync_status' => TicketMessage::DISCORD_SYNC_FAILED,
                'discord_sync_error' => $this->discordErrorMessage($response, 'Unable to relay the ticket message to Discord.'),
            ])->saveOrFail();

            $ticket->forceFill([
                'discord_sync_status' => Ticket::DISCORD_SYNC_FAILED,
                'discord_last_error' => $message->discord_sync_error,
            ])->saveOrFail();

            throw new RuntimeException($message->discord_sync_error ?: 'Unable to relay the ticket message to Discord.');
        }

        $message->forceFill([
            'discord_message_id' => (string) Arr::get($response->json(), 'id', $message->discord_message_id),
            'discord_sync_status' => TicketMessage::DISCORD_SYNC_SYNCED,
            'discord_synced_at' => now(),
            'discord_sync_error' => null,
        ])->saveOrFail();

        $ticket->forceFill([
            'discord_sync_status' => Ticket::DISCORD_SYNC_SYNCED,
            'discord_last_synced_at' => now(),
            'discord_last_error' => null,
        ])->saveOrFail();

        return $message->fresh(['attachments']);
    }

    public function scheduleTicketSyncAfterResponse(int $ticketId): void
    {
        app()->terminating(function () use ($ticketId) {
            $ticket = Ticket::query()
                ->with(['messages.attachments', 'messages.author', 'assignedAdmin', 'invoice', 'payment', 'subscription', 'order'])
                ->find($ticketId);

            if (!$ticket) {
                return;
            }

            try {
                $ticket = $this->ensureTicketThread($ticket);
                $this->syncPendingMessages($ticket);
            } catch (\Throwable $exception) {
                report($exception);
            }
        });
    }

    public function scheduleMessageRelayAfterResponse(int $messageId): void
    {
        app()->terminating(function () use ($messageId) {
            $message = TicketMessage::query()->with(['ticket', 'attachments', 'author'])->find($messageId);
            if (!$message) {
                return;
            }

            try {
                $this->relayMessage($message);
            } catch (\Throwable $exception) {
                report($exception);
            }
        });
    }

    public function syncPendingMessages(Ticket $ticket): Ticket
    {
        $ticket->loadMissing('messages.attachments', 'messages.author');

        foreach ($ticket->messages->sortBy('id') as $message) {
            if ($message->deleted_at || $message->discord_message_id || $message->discord_sync_status === TicketMessage::DISCORD_SYNC_SYNCED) {
                continue;
            }

            $this->relayMessage($message);
        }

        return $ticket->fresh(['messages.attachments', 'assignedAdmin', 'invoice', 'payment', 'subscription', 'order']);
    }

    public function createRelayWebhook(?string $channelId = null, ?string $botToken = null, ?string $name = null): array
    {
        $channelId = trim((string) ($channelId ?? $this->settings->activeParentChannelId() ?? ''));
        $botToken = trim((string) ($botToken ?? config('services.discord.bot_token', '')));
        $name = Str::limit(trim((string) ($name ?? config('app.name', 'Panel') . ' Support Relay')), 80, '');

        if ($channelId === '' || $botToken === '') {
            throw new RuntimeException('Discord active parent channel or bot token is missing.');
        }

        $response = $this->botHttp($botToken)->post(
            sprintf('%s/channels/%s/webhooks', self::API_BASE, $channelId),
            ['name' => $name]
        );

        if (!$response->successful()) {
            throw new RuntimeException($this->discordErrorMessage($response, 'Unable to create the Discord relay webhook automatically.'));
        }

        $webhookId = Arr::get($response->json(), 'id');
        $webhookToken = Arr::get($response->json(), 'token');

        if (!is_string($webhookId) || trim($webhookId) === '' || !is_string($webhookToken) || trim($webhookToken) === '') {
            throw new RuntimeException('Discord did not return a usable relay webhook identifier and token.');
        }

        return [
            'id' => $webhookId,
            'token' => $webhookToken,
            'channel_id' => $channelId,
            'name' => $name,
        ];
    }

    public function generateBridgeSharedSecret(int $length = 64): string
    {
        return Str::random(max($length, 32));
    }

    public function syncLauncherMessage(): array
    {
        if (!$this->settings->hasBotToken() || !$this->settings->launcherChannelId()) {
            throw new RuntimeException('Discord launcher channel or bot token is missing.');
        }

        $payload = $this->launcherPayload();
        $messageId = $this->settings->launcherMessageId();

        $response = null;

        if ($messageId) {
            $response = $this->botHttp()->patch(
                sprintf('%s/channels/%s/messages/%s', self::API_BASE, $this->settings->launcherChannelId(), $messageId),
                $payload
            );

            // Stored launcher message IDs can go stale if the Discord message is deleted manually.
            if ($response->status() === 404 && str_contains(strtolower($this->discordErrorMessage($response, '')), 'unknown message')) {
                $response = null;
            }
        }

        if (!$response) {
            $response = $this->botHttp()->post(
                sprintf('%s/channels/%s/messages', self::API_BASE, $this->settings->launcherChannelId()),
                $payload
            );
        }

        if (!$response->successful()) {
            throw new RuntimeException($this->discordErrorMessage($response, 'Unable to sync the Discord launcher embed.'));
        }

        return is_array($response->json()) ? $response->json() : [];
    }

    public function launcherPayload(): array
    {
        return [
            'content' => null,
            'embeds' => [$this->launcherEmbed(
                "Open a private support ticket for payment, refund, or server help.\n\nChoose a ticket type from the dropdown. For support tickets, Discord will ask for the server ID you want help with and then ask only for your message.",
                'Linked Discord account required. The launcher resets after each ticket is opened.'
            )],
            'components' => [
                [
                    'type' => 1,
                    'components' => [
                        [
                            'type' => 3,
                            'custom_id' => 'tickets:open:type',
                            'placeholder' => 'Choose ticket type',
                            'min_values' => 1,
                            'max_values' => 1,
                            'options' => [
                                [
                                    'label' => 'Payment',
                                    'value' => Ticket::CATEGORY_PAYMENT,
                                    'description' => 'Open a ticket for an unpaid invoice or payment issue.',
                                ],
                                [
                                    'label' => 'Refund',
                                    'value' => Ticket::CATEGORY_REFUND,
                                    'description' => 'Open a ticket for a payment refund request.',
                                ],
                                [
                                    'label' => 'Support',
                                    'value' => Ticket::CATEGORY_SUPPORT,
                                    'description' => 'Choose a server you can access and send your message.',
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    'type' => 1,
                    'components' => [
                        [
                            'type' => 2,
                            'style' => 5,
                            'label' => 'Payment in Panel',
                            'url' => $this->urls->composeUrl(Ticket::CATEGORY_PAYMENT),
                        ],
                        [
                            'type' => 2,
                            'style' => 5,
                            'label' => 'Refund in Panel',
                            'url' => $this->urls->composeUrl(Ticket::CATEGORY_REFUND),
                        ],
                        [
                            'type' => 2,
                            'style' => 5,
                            'label' => 'Support Center',
                            'url' => $this->urls->composeUrl(Ticket::CATEGORY_SUPPORT),
                        ],
                    ],
                ],
            ],
        ];
    }

    public function launcherStepPayload(string $description, array $components, ?string $footer = null): array
    {
        return [
            'content' => null,
            'embeds' => [$this->launcherEmbed(
                $description,
                $footer ?: 'This launcher resets after each ticket is opened.'
            )],
            'components' => $components,
        ];
    }

    public function closeTicketThread(Ticket $ticket, ?string $reason = null): Ticket
    {
        if (!$ticket->discord_thread_id || !$this->settings->hasBotToken()) {
            return $ticket->fresh();
        }

        $request = $this->botHttp();
        if ($reason) {
            $request = $request->withHeaders([
                'X-Audit-Log-Reason' => rawurlencode($reason),
            ]);
        }

        $response = $request->delete(sprintf('%s/channels/%s', self::API_BASE, $ticket->discord_thread_id));

        if (
            !$response->successful()
            && !str_contains(strtolower($this->discordErrorMessage($response, '')), 'unknown channel')
        ) {
            $ticket->forceFill([
                'discord_sync_status' => Ticket::DISCORD_SYNC_FAILED,
                'discord_last_error' => $this->discordErrorMessage($response, 'Unable to close the Discord ticket thread.'),
            ])->saveOrFail();

            throw new RuntimeException($ticket->discord_last_error ?: 'Unable to close the Discord ticket thread.');
        }

        $ticket->forceFill([
            'discord_thread_id' => null,
            'discord_parent_channel_id' => null,
            'discord_sync_status' => Ticket::DISCORD_SYNC_SKIPPED,
            'discord_last_synced_at' => now(),
            'discord_last_error' => null,
        ])->saveOrFail();

        return $ticket->fresh();
    }

    public function logAudit(string $content): void
    {
        if (!$this->settings->logChannelId() || !$this->settings->hasBotToken()) {
            return;
        }

        $this->botHttp()->post(
            sprintf('%s/channels/%s/messages', self::API_BASE, $this->settings->logChannelId()),
            ['content' => Str::limit($content, 1900)]
        );
    }

    private function postThreadIntro(Ticket $ticket): void
    {
        if (!$ticket->discord_thread_id) {
            return;
        }

        $staffMentions = $this->staffRoleMentions();
        $adminUrl = $this->urls->adminTicketUrl($ticket);
        $clientUrl = $this->urls->clientTicketUrl($ticket);
        $lines = array_filter([
            $staffMentions !== '' ? 'Staff alert: ' . $staffMentions : null,
            sprintf('Ticket %s has been created.', $ticket->ticket_number),
            'Category: ' . strtoupper($ticket->category),
            $ticket->invoice?->invoice_number ? 'Invoice: ' . $ticket->invoice->invoice_number : null,
            $ticket->payment?->payment_number ? 'Payment: ' . $ticket->payment->payment_number : null,
            'Admin: ' . $adminUrl,
            'User: ' . $clientUrl,
        ]);

        $payload = [
            'content' => implode("\n", $lines),
            'components' => [[
                'type' => 1,
                'components' => [
                    [
                        'type' => 2,
                        'style' => 5,
                        'label' => 'Open Admin Ticket',
                        'url' => $adminUrl,
                    ],
                    [
                        'type' => 2,
                        'style' => 5,
                        'label' => 'Open User Ticket',
                        'url' => $clientUrl,
                    ],
                    [
                        'type' => 2,
                        'style' => 4,
                        'label' => 'Close Ticket',
                        'custom_id' => 'tickets:thread:close',
                    ],
                ],
            ]],
        ];

        if ($staffMentions !== '') {
            $payload['allowed_mentions'] = [
                'parse' => [],
                'roles' => $this->settings->staffRoleIds(),
            ];
        }

        $this->botHttp()->post(
            sprintf('%s/channels/%s/messages', self::API_BASE, $ticket->discord_thread_id),
            $payload
        );
    }

    private function syncInitialThreadMembers(Ticket $ticket, string $threadId): array
    {
        $warnings = [];
        $members = [];

        $requesterId = trim((string) $ticket->requester_discord_user_id);
        if ($requesterId !== '') {
            $members[$requesterId] = 'requester';
        }

        foreach ($this->staffDiscordMemberIds($ticket) as $discordUserId) {
            $members[$discordUserId] = $members[$discordUserId] ?? 'staff';
        }

        foreach ($members as $discordUserId => $memberType) {
            $response = $this->botHttp()->put(
                sprintf('%s/channels/%s/thread-members/%s', self::API_BASE, $threadId, $discordUserId)
            );

            if ($response->successful() || $response->status() === 204) {
                continue;
            }

            $warnings[] = sprintf(
                'Unable to add the %s Discord account %s to the thread: %s',
                $memberType,
                $discordUserId,
                $this->discordErrorMessage($response, 'Unknown Discord API error.')
            );
        }

        return $warnings;
    }

    private function staffDiscordMemberIds(Ticket $ticket): array
    {
        return UserOAuthAccount::query()
            ->where('provider', 'discord')
            ->whereNotNull('provider_id')
            ->where('provider_id', '!=', '')
            ->where(function ($query) use ($ticket) {
                $query->whereHas('user', fn ($userQuery) => $userQuery->where('root_admin', true));

                if ($ticket->assigned_admin_id) {
                    $query->orWhere('user_id', $ticket->assigned_admin_id);
                }
            })
            ->pluck('provider_id')
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function staffRoleMentions(): string
    {
        $roles = $this->settings->staffRoleIds();

        return implode(' ', array_map(
            fn (string $roleId) => sprintf('<@&%s>', $roleId),
            array_values(array_filter(array_map('trim', $roles)))
        ));
    }

    private function launcherEmbed(string $description, string $footer): array
    {
        return [
            'title' => config('app.name', 'Panel') . ' Support Tickets',
            'description' => $description,
            'color' => 0xF0B90B,
            'footer' => ['text' => $footer],
        ];
    }

    private function botHttp(?string $botToken = null): PendingRequest
    {
        return Http::acceptJson()
            ->withToken(trim((string) ($botToken ?? config('services.discord.bot_token', ''))), 'Bot')
            ->timeout((int) config('pterodactyl.guzzle.timeout', 30))
            ->connectTimeout((int) config('pterodactyl.guzzle.connect_timeout', 10));
    }

    private function webhookMultipartHttp(): PendingRequest
    {
        return Http::acceptJson()
            ->asMultipart()
            ->timeout((int) config('pterodactyl.guzzle.timeout', 30))
            ->connectTimeout((int) config('pterodactyl.guzzle.connect_timeout', 10));
    }

    private function webhookUrl(string $threadId): string
    {
        return sprintf(
            '%s/webhooks/%s/%s?wait=true&thread_id=%s',
            self::API_BASE,
            $this->settings->relayWebhookId(),
            $this->settings->relayWebhookToken(),
            $threadId
        );
    }

    private function discordContent(?string $body): ?string
    {
        $body = trim((string) $body);
        if ($body === '') {
            return null;
        }

        $suffix = "\n\n[Truncated in Discord. Open the panel for the full message.]";
        if (mb_strlen($body) <= 2000) {
            return $body;
        }

        return rtrim(Str::limit($body, 2000 - mb_strlen($suffix), '')) . $suffix;
    }

    private function discordUsername(TicketMessage $message): string
    {
        $username = trim((string) ($message->author_display_name ?: ($message->author?->username ?: config('app.name', 'Panel'))));

        if ($username === '') {
            $username = config('app.name', 'Panel');
        }

        return Str::limit($username, 80, '');
    }

    private function discordAvatarUrl(TicketMessage $message): ?string
    {
        $candidate = trim((string) ($message->author_avatar_url ?: ($message->author?->getImageUrl() ?: '')));
        if ($candidate === '') {
            return null;
        }

        if (filter_var($candidate, FILTER_VALIDATE_URL)) {
            return $candidate;
        }

        $base = rtrim((string) config('app.url', ''), '/');
        if ($base === '') {
            return null;
        }

        $absolute = $base . '/' . ltrim($candidate, '/');

        return filter_var($absolute, FILTER_VALIDATE_URL) ? $absolute : null;
    }

    private function threadName(Ticket $ticket): string
    {
        return Str::limit(
            sprintf('%s %s', $ticket->ticket_number, $ticket->subject),
            95,
            ''
        );
    }

    private function threadIsAccessible(string $threadId): bool
    {
        if (!$this->settings->hasBotToken()) {
            return false;
        }

        $response = $this->botHttp()->get(sprintf('%s/channels/%s', self::API_BASE, $threadId));

        return $response->successful();
    }

    private function discordErrorMessage(Response $response, string $fallback): string
    {
        $message = Arr::get($response->json(), 'message');
        $details = $this->flattenDiscordErrors(Arr::get($response->json(), 'errors', []));
        if (is_string($message) && trim($message) !== '' && $details !== '') {
            return sprintf('%s (%s)', $message, $details);
        }

        if (is_string($message) && trim($message) !== '') {
            return $message;
        }

        return $fallback;
    }

    private function flattenDiscordErrors(array $errors, string $prefix = ''): string
    {
        $parts = [];

        foreach ($errors as $key => $value) {
            $path = $prefix !== '' ? $prefix . '.' . $key : (string) $key;

            if ($key === '_errors' && is_array($value)) {
                foreach ($value as $entry) {
                    $message = trim((string) Arr::get($entry, 'message', ''));
                    if ($message !== '') {
                        $parts[] = $prefix !== '' ? $prefix . ': ' . $message : $message;
                    }
                }

                continue;
            }

            if (is_array($value)) {
                $nested = $this->flattenDiscordErrors($value, $path);
                if ($nested !== '') {
                    $parts[] = $nested;
                }
            }
        }

        return implode('; ', array_filter($parts));
    }
}
