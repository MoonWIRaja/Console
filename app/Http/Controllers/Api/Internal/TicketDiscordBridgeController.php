<?php

namespace Pterodactyl\Http\Controllers\Api\Internal;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Pterodactyl\Models\UserOAuthAccount;
use Pterodactyl\Models\Ticket;
use Pterodactyl\Models\TicketMessage;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Tickets\TicketService;
use Pterodactyl\Services\Tickets\TicketDiscordService;
use Pterodactyl\Services\Tickets\TicketDiscordInteractionService;
use Pterodactyl\Services\Tickets\TicketMessageService;
use Pterodactyl\Services\Tickets\TicketSettingsService;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;

class TicketDiscordBridgeController extends Controller
{
    public function __construct(
        private TicketService $tickets,
        private TicketMessageService $messages,
        private TicketSettingsService $settings,
        private TicketDiscordService $discord,
        private TicketDiscordInteractionService $interactions,
        private SettingsRepositoryInterface $repository,
    ) {
    }

    public function interactions(Request $request): JsonResponse
    {
        $this->abortIfInvalidSignature($request);

        $payload = $request->validate([
            'payload' => 'required|array',
        ]);

        return new JsonResponse(
            $this->interactions->handle($payload['payload']),
            Response::HTTP_OK
        );
    }

    public function events(Request $request): JsonResponse
    {
        $this->abortIfInvalidSignature($request);

        $payload = $request->validate([
            'event_type' => 'required|string|max:64',
            'ticket_id' => 'nullable|integer|exists:tickets,id',
            'thread.id' => 'nullable|string|max:32',
            'message' => 'nullable|array',
        ]);

        $ticket = isset($payload['ticket_id'])
            ? Ticket::query()->findOrFail($payload['ticket_id'])
            : Ticket::query()
                ->where('discord_thread_id', (string) Arr::get($payload, 'thread.id', Arr::get($payload, 'message.channel_id', '')))
                ->firstOrFail();
        $messagePayload = $payload['message'] ?? [];

        if (in_array($payload['event_type'], ['MESSAGE_CREATE', 'MESSAGE_UPDATE', 'MESSAGE_DELETE'], true) && is_array($messagePayload)) {
            $authorDiscordId = (string) ($messagePayload['author']['id'] ?? '');
            $authorAccount = $authorDiscordId !== ''
                ? UserOAuthAccount::query()
                    ->with('user')
                    ->where('provider', 'discord')
                    ->where('provider_id', $authorDiscordId)
                    ->first()
                : null;

            $authorType = null;
            if ($ticket->requester_discord_user_id === $authorDiscordId) {
                $authorType = TicketMessage::AUTHOR_USER;
            } elseif ($authorAccount?->user?->root_admin && $this->discordStaffAllowed($messagePayload)) {
                $authorType = TicketMessage::AUTHOR_ADMIN;
            }

            if (!$authorType) {
                $this->discord->logAudit(sprintf(
                    'Ignored Discord ticket event %s for ticket %s from unauthorised user %s.',
                    $payload['event_type'],
                    $ticket->ticket_number,
                    $authorDiscordId !== '' ? $authorDiscordId : 'unknown'
                ));

                return new JsonResponse(['data' => ['ok' => true, 'ignored' => true]], Response::HTTP_ACCEPTED);
            }

            $this->messages->importDiscordMessage($ticket, [
                'message_id' => $messagePayload['id'] ?? null,
                'body' => $messagePayload['content'] ?? '',
                'attachments' => $messagePayload['attachments'] ?? [],
                'edited_at' => $messagePayload['edited_timestamp'] ?? null,
                'deleted_at' => $payload['event_type'] === 'MESSAGE_DELETE' ? now()->toIso8601String() : null,
                'author_type' => $authorType,
                'author_user_id' => $authorAccount?->user_id,
                'author_display_name' => $authorAccount?->display_name ?? ($messagePayload['author']['global_name'] ?? $messagePayload['author']['username'] ?? 'Discord User'),
                'author_avatar_url' => $authorAccount?->avatar,
                'meta' => [
                    'event_type' => $payload['event_type'],
                    'thread_id' => $payload['thread']['id'] ?? $ticket->discord_thread_id,
                ],
            ]);
        }

        if ($payload['event_type'] === 'THREAD_DELETE') {
            $ticket->forceFill(
                $ticket->status === Ticket::STATUS_CLOSED
                    ? [
                        'discord_thread_id' => null,
                        'discord_parent_channel_id' => null,
                        'discord_sync_status' => Ticket::DISCORD_SYNC_SKIPPED,
                        'discord_last_synced_at' => now(),
                        'discord_last_error' => null,
                    ]
                    : [
                        'discord_sync_status' => Ticket::DISCORD_SYNC_FAILED,
                        'discord_last_error' => 'The linked Discord thread was deleted.',
                    ]
            )->saveOrFail();
        } elseif ($payload['event_type'] === 'THREAD_UPDATE') {
            $ticket->forceFill([
                'discord_sync_status' => Ticket::DISCORD_SYNC_SYNCED,
                'discord_last_synced_at' => now(),
                'discord_last_error' => Arr::get($payload, 'thread.archived')
                    ? 'The Discord thread is archived. New replies will reopen activity on the panel side.'
                    : null,
            ])->saveOrFail();
        }

        return new JsonResponse(['data' => ['ok' => true]], Response::HTTP_ACCEPTED);
    }

    public function heartbeat(Request $request): JsonResponse
    {
        $this->abortIfInvalidSignature($request);

        $meta = $request->validate([
            'status' => 'nullable|string|max:32',
            'shard' => 'nullable|integer',
            'gateway_ping_ms' => 'nullable|numeric',
            'uptime_seconds' => 'nullable|integer',
        ]);

        $this->repository->set('settings::tickets:bridge:last_heartbeat_at', now()->toIso8601String());
        $this->repository->set('settings::tickets:bridge:last_heartbeat_meta', json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return new JsonResponse(['data' => ['ok' => true]], Response::HTTP_ACCEPTED);
    }

    private function abortIfInvalidSignature(Request $request): void
    {
        $secret = (string) $this->settings->bridgeSharedSecret();
        abort_if($secret === '', 403);

        $provided = (string) $request->header('X-Tickets-Signature', '');
        $expected = hash_hmac('sha256', (string) $request->getContent(), $secret);

        abort_unless(hash_equals($expected, $provided), 403);
    }

    private function discordStaffAllowed(array $messagePayload): bool
    {
        $requiredRoles = $this->settings->staffRoleIds();
        if ($requiredRoles === []) {
            return true;
        }

        $roles = array_values(array_filter(array_map(
            'strval',
            Arr::wrap(Arr::get($messagePayload, 'member.roles', Arr::get($messagePayload, 'author.roles', [])))
        )));

        return array_intersect($requiredRoles, $roles) !== [];
    }
}
