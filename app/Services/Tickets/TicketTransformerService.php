<?php

namespace Pterodactyl\Services\Tickets;

use Pterodactyl\Models\User;
use Pterodactyl\Models\Ticket;
use Pterodactyl\Models\TicketMessage;

class TicketTransformerService
{
    public function __construct(
        private TicketAttachmentService $attachments,
        private TicketUrlService $urls,
    ) {
    }

    public function summary(Ticket $ticket, User $viewer): array
    {
        $ticket->loadMissing(['assignedAdmin', 'invoice', 'payment', 'subscription', 'order']);

        return [
            'support_server_id' => $ticket->meta['support_server_id'] ?? null,
            'support_server_name' => $ticket->meta['support_server_name'] ?? null,
            'support_server_uuid_short' => $ticket->meta['support_server_uuid_short'] ?? null,
            'id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'category' => $ticket->category,
            'source' => $ticket->source,
            'status' => $ticket->status,
            'subject' => $ticket->subject,
            'assigned_admin' => $ticket->assignedAdmin ? [
                'id' => $ticket->assignedAdmin->id,
                'username' => $ticket->assignedAdmin->username,
            ] : null,
            'billing_order_id' => $ticket->billing_order_id,
            'billing_invoice_id' => $ticket->billing_invoice_id,
            'billing_payment_id' => $ticket->billing_payment_id,
            'billing_subscription_id' => $ticket->billing_subscription_id,
            'invoice_number' => $ticket->invoice?->invoice_number,
            'payment_number' => $ticket->payment?->payment_number,
            'server_name' => $ticket->subscription?->server_name ?? $ticket->order?->server_name ?? ($ticket->meta['support_server_name'] ?? null),
            'discord_thread_id' => $ticket->discord_thread_id,
            'discord_thread_url' => $this->discordThreadUrl($ticket),
            'discord_sync_status' => $ticket->discord_sync_status,
            'discord_last_error' => $ticket->discord_last_error,
            'last_user_message_at' => optional($ticket->last_user_message_at)->toIso8601String(),
            'last_admin_message_at' => optional($ticket->last_admin_message_at)->toIso8601String(),
            'resolved_at' => optional($ticket->resolved_at)->toIso8601String(),
            'closed_at' => optional($ticket->closed_at)->toIso8601String(),
            'created_at' => optional($ticket->created_at)->toIso8601String(),
            'updated_at' => optional($ticket->updated_at)->toIso8601String(),
            'unread' => $this->isUnread($ticket, $viewer),
            'url' => $this->urls->clientTicketUrl($ticket),
        ];
    }

    public function detail(Ticket $ticket, User $viewer): array
    {
        $ticket->loadMissing([
            'assignedAdmin',
            'user',
            'invoice.order',
            'invoice.subscription',
            'payment.invoice',
            'subscription',
            'order',
            'messages.attachments',
        ]);

        return $this->summary($ticket, $viewer) + [
            'user' => [
                'id' => $ticket->user?->id,
                'username' => $ticket->user?->username,
                'email' => $ticket->user?->email,
            ],
            'messages' => $ticket->messages
                ->sortBy('id')
                ->values()
                ->map(fn (TicketMessage $message) => $this->message($message))
                ->all(),
            'meta' => $ticket->meta ?? [],
        ];
    }

    public function message(TicketMessage $message): array
    {
        $message->loadMissing('attachments');

        return [
            'id' => $message->id,
            'author_type' => $message->author_type,
            'author_user_id' => $message->author_user_id,
            'author_display_name' => $message->author_display_name,
            'author_avatar_url' => $message->author_avatar_url,
            'origin' => $message->origin,
            'body' => $message->body,
            'discord_message_id' => $message->discord_message_id,
            'discord_sync_status' => $message->discord_sync_status,
            'discord_sync_error' => $message->discord_sync_error,
            'edited_at' => optional($message->edited_at)->toIso8601String(),
            'deleted_at' => optional($message->deleted_at)->toIso8601String(),
            'created_at' => optional($message->created_at)->toIso8601String(),
            'attachments' => $message->attachments
                ->map(fn ($attachment) => $this->attachments->toArray($attachment))
                ->values()
                ->all(),
        ];
    }

    private function isUnread(Ticket $ticket, User $viewer): bool
    {
        if ($viewer->root_admin) {
            return $ticket->last_user_message_at
                && (!$ticket->staff_last_read_at || $ticket->last_user_message_at->gt($ticket->staff_last_read_at));
        }

        return $ticket->last_admin_message_at
            && (!$ticket->user_last_read_at || $ticket->last_admin_message_at->gt($ticket->user_last_read_at));
    }

    private function discordThreadUrl(Ticket $ticket): ?string
    {
        if (!$ticket->discord_thread_id || !config('services.discord.guild_id')) {
            return null;
        }

        return sprintf(
            'https://discord.com/channels/%s/%s',
            config('services.discord.guild_id'),
            $ticket->discord_thread_id
        );
    }
}
