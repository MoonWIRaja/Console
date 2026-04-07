<?php

namespace Pterodactyl\Services\Tickets;

use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Ticket;
use Pterodactyl\Models\TicketMessage;

class TicketMessageService
{
    public function __construct(private TicketAttachmentService $attachmentService)
    {
    }

    public function postUserMessage(Ticket $ticket, User $user, string $body, array $files = [], array $meta = []): TicketMessage
    {
        return $this->postMessage(
            $ticket,
            TicketMessage::AUTHOR_USER,
            $user,
            $body,
            TicketMessage::ORIGIN_CONSOLE,
            $files,
            $meta
        );
    }

    public function postAdminMessage(Ticket $ticket, User $user, string $body, array $files = [], array $meta = []): TicketMessage
    {
        return $this->postMessage(
            $ticket,
            TicketMessage::AUTHOR_ADMIN,
            $user,
            $body,
            TicketMessage::ORIGIN_CONSOLE,
            $files,
            $meta
        );
    }

    public function postSystemMessage(Ticket $ticket, string $body, array $meta = []): TicketMessage
    {
        return $this->postMessage(
            $ticket,
            TicketMessage::AUTHOR_SYSTEM,
            null,
            $body,
            TicketMessage::ORIGIN_AUTOMATION,
            [],
            $meta
        );
    }

    public function importDiscordMessage(Ticket $ticket, array $payload): TicketMessage
    {
        $author = Arr::get($payload, 'author', []);
        $attachments = Arr::wrap(Arr::get($payload, 'attachments', []));
        $body = (string) Arr::get($payload, 'body', '');
        $contentUnavailable = trim($body) === '' && $attachments === [];

        if ($contentUnavailable) {
            $body = '[Discord message text is unavailable because the bot does not have the Message Content intent enabled in the Discord Developer Portal.]';
        }

        $message = TicketMessage::query()->firstOrNew([
            'discord_message_id' => (string) Arr::get($payload, 'message_id'),
        ]);

        $message->forceFill([
            'ticket_id' => $ticket->id,
            'author_type' => (string) Arr::get($payload, 'author_type', TicketMessage::AUTHOR_USER),
            'author_user_id' => Arr::get($payload, 'author_user_id'),
            'author_display_name' => Arr::get($payload, 'author_display_name', Arr::get($author, 'display_name')),
            'author_avatar_url' => Arr::get($payload, 'author_avatar_url', Arr::get($author, 'avatar_url')),
            'origin' => TicketMessage::ORIGIN_DISCORD,
            'body' => $body,
            'discord_message_id' => (string) Arr::get($payload, 'message_id'),
            'discord_sync_status' => TicketMessage::DISCORD_SYNC_SYNCED,
            'discord_synced_at' => CarbonImmutable::now(),
            'discord_sync_error' => null,
            'edited_at' => Arr::get($payload, 'edited_at') ? CarbonImmutable::parse((string) Arr::get($payload, 'edited_at')) : null,
            'deleted_at' => Arr::get($payload, 'deleted_at') ? CarbonImmutable::parse((string) Arr::get($payload, 'deleted_at')) : null,
            'meta' => array_merge(
                Arr::get($payload, 'meta', []),
                $contentUnavailable ? ['discord_content_unavailable' => true] : []
            ),
        ])->saveOrFail();

        foreach ($attachments as $attachment) {
            if (!is_array($attachment) || blank($attachment['url'] ?? null)) {
                continue;
            }

            if ($message->attachments()->where('discord_attachment_id', (string) ($attachment['id'] ?? ''))->exists()) {
                continue;
            }

            $this->attachmentService->importFromRemote(
                $message,
                (string) $attachment['url'],
                (string) ($attachment['filename'] ?? 'attachment'),
                $attachment['content_type'] ?? null,
                $attachment['id'] ?? null
            );
        }

        $this->touchTicketAfterMessage($ticket->fresh(), $message);

        return $message->fresh(['attachments']);
    }

    /**
     * @param array<int, mixed> $files
     */
    private function postMessage(
        Ticket $ticket,
        string $authorType,
        ?User $user,
        string $body,
        string $origin,
        array $files = [],
        array $meta = []
    ): TicketMessage {
        $message = DB::transaction(function () use ($ticket, $authorType, $user, $body, $origin, $files, $meta) {
            $message = TicketMessage::query()->create([
                'ticket_id' => $ticket->id,
                'author_type' => $authorType,
                'author_user_id' => $user?->id,
                'author_display_name' => $user?->username ?? config('app.name', 'System'),
                'author_avatar_url' => $user?->getImageUrl(),
                'origin' => $origin,
                'body' => $body,
                'discord_sync_status' => TicketMessage::DISCORD_SYNC_PENDING,
                'meta' => $meta,
            ]);

            $attachments = $this->attachmentService->storeUploads($message, $files);
            if ($attachments !== []) {
                $message->load('attachments');
            }

            $this->touchTicketAfterMessage($ticket->fresh(), $message);

            return $message->fresh(['attachments']);
        });

        $event = match ($authorType) {
            TicketMessage::AUTHOR_ADMIN => 'ticket:message.staff',
            TicketMessage::AUTHOR_SYSTEM => 'ticket:message.system',
            default => 'ticket:message.user',
        };

        $activity = Activity::event($event)
            ->subject($ticket->fresh(), $message)
            ->property([
                'ticket_number' => $ticket->ticket_number,
                'author_type' => $authorType,
                'origin' => $origin,
                'body_preview' => mb_substr(trim($body), 0, 160),
            ]);

        if ($user instanceof User) {
            $activity->subject($user);
        }

        $activity->log();

        return $message;
    }

    private function touchTicketAfterMessage(Ticket $ticket, TicketMessage $message): void
    {
        if ($message->author_type === TicketMessage::AUTHOR_SYSTEM) {
            $ticket->forceFill([
                'updated_at' => CarbonImmutable::now(),
            ])->saveOrFail();

            return;
        }

        $isAdmin = $message->author_type === TicketMessage::AUTHOR_ADMIN;
        $now = CarbonImmutable::now();

        $payload = [
            'status' => $isAdmin ? Ticket::STATUS_WAITING_FOR_USER : Ticket::STATUS_WAITING_FOR_STAFF,
            'resolved_at' => null,
            'closed_at' => null,
            'updated_at' => $now,
        ];

        if ($isAdmin) {
            $payload['last_admin_message_at'] = $now;
            $payload['staff_last_read_at'] = $now;
            if (!$ticket->assigned_admin_id && $message->author_user_id) {
                $payload['assigned_admin_id'] = $message->author_user_id;
            }
        } else {
            $payload['last_user_message_at'] = $now;
            $payload['user_last_read_at'] = $now;
        }

        $ticket->forceFill($payload)->saveOrFail();
    }
}
