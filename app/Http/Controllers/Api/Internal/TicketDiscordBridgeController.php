<?php

namespace Pterodactyl\Http\Controllers\Api\Internal;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Pterodactyl\Models\UserOAuthAccount;
use Pterodactyl\Models\Ticket;
use Pterodactyl\Models\TicketMessage;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\DownDetector\DownDetectorDiscordInteractionService;
use Pterodactyl\Services\Security\SecurityOrchestratorService;
use Pterodactyl\Services\Security\SecurityVocabulary;
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
        private DownDetectorDiscordInteractionService $downDetectorInteractions,
        private SettingsRepositoryInterface $repository,
        private SecurityOrchestratorService $orchestrator,
    ) {
    }

    public function interactions(Request $request): JsonResponse
    {
        $this->abortIfInvalidSignature($request);

        $payload = $request->validate([
            'payload' => 'required|array',
        ]);

        $customId = (string) data_get($payload, 'payload.data.custom_id', '');
        $service = str_starts_with($customId, 'down-detector:')
            ? $this->downDetectorInteractions
            : $this->interactions;

        return new JsonResponse(
            $service->handle($payload['payload']),
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
            $isRequester = $ticket->requester_discord_user_id === $authorDiscordId
                || ((int) ($authorAccount?->user_id ?? 0) > 0 && (int) $authorAccount->user_id === (int) $ticket->user_id);

            if ($isRequester) {
                $this->syncRequesterDiscordIdentity($ticket, $authorAccount, $authorDiscordId);
                $authorType = TicketMessage::AUTHOR_USER;
            } elseif ($this->canAuthorAsStaff($ticket, $authorAccount, $messagePayload)) {
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
            if ($ticket->status !== Ticket::STATUS_CLOSED) {
                $ticket = $this->tickets->updateStatus($ticket, Ticket::STATUS_CLOSED);
            }

            $ticket->forceFill(
                [
                    'discord_thread_id' => null,
                    'discord_parent_channel_id' => null,
                    'discord_sync_status' => Ticket::DISCORD_SYNC_SKIPPED,
                    'discord_last_synced_at' => now(),
                    'discord_last_error' => null,
                ]
            )->saveOrFail();
        } elseif ($payload['event_type'] === 'THREAD_UPDATE') {
            $threadArchived = (bool) Arr::get($payload, 'thread.archived', false);
            $threadLocked = (bool) Arr::get($payload, 'thread.locked', false);

            if (($threadArchived || $threadLocked) && $ticket->status !== Ticket::STATUS_CLOSED) {
                $ticket = $this->tickets->updateStatus($ticket, Ticket::STATUS_CLOSED);
            }

            $ticket->forceFill([
                'discord_sync_status' => Ticket::DISCORD_SYNC_SYNCED,
                'discord_last_synced_at' => now(),
                'discord_last_error' => null,
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
        $timestamp = (int) $request->header('X-Tickets-Timestamp', 0);
        $nonce = trim((string) $request->header('X-Tickets-Nonce', ''));
        $provided = (string) $request->header('X-Tickets-Signature', '');
        $path = $request->path();

        if ($secret === '') {
            $this->recordBridgeFailure($request, 'shared_secret_missing', 'Ticket bridge shared secret is not configured; request rejected.', [
                'path' => $path,
                'secret_configured' => false,
                'provided_signature_prefix' => $provided !== '' ? substr($provided, 0, 16) : null,
            ], false);

            abort(403);
        }

        if ($timestamp <= 0 || $nonce === '' || $provided === '') {
            $this->recordBridgeFailure($request, 'missing_headers', 'Ticket bridge signing headers were incomplete.', [
                'path' => $path,
                'secret_configured' => true,
                'timestamp_present' => $timestamp > 0,
                'nonce_present' => $nonce !== '',
                'signature_present' => $provided !== '',
            ]);

            abort(403);
        }

        if (abs(now()->timestamp - $timestamp) > $this->settings->bridgeClockSkewSeconds()) {
            $this->recordBridgeFailure($request, 'clock_skew', 'Ticket bridge timestamp exceeded the allowed clock skew.', [
                'path' => $path,
                'secret_configured' => true,
                'timestamp' => $timestamp,
                'clock_skew_seconds' => $this->settings->bridgeClockSkewSeconds(),
            ]);

            abort(403);
        }

        $body = (string) $request->getContent();
        $expected = hash_hmac('sha256', $timestamp . "\n" . $nonce . "\n" . $body, $secret);

        if (!hash_equals($expected, $provided)) {
            $this->recordBridgeFailure($request, 'signature_mismatch', 'Ticket bridge request signature validation failed.', [
                'path' => $path,
                'secret_configured' => true,
                'provided_signature_prefix' => substr($provided, 0, 16),
            ]);

            abort(403);
        }

        $nonceKey = sprintf('tickets:bridge:nonce:%s', sha1($nonce));
        if (!$this->cache()->add($nonceKey, true, now()->addSeconds($this->settings->bridgeNonceTtlSeconds()))) {
            $this->recordBridgeFailure($request, 'replay', 'Ticket bridge nonce replay detected and rejected.', [
                'path' => $path,
                'secret_configured' => true,
                'nonce_prefix' => substr($nonce, 0, 16),
                'timestamp' => $timestamp,
            ]);

            abort(403);
        }
    }

    private function recordBridgeFailure(
        Request $request,
        string $reason,
        string $summary,
        array $evidence = [],
        bool $executeActions = true,
    ): void {
        \Illuminate\Support\Facades\Log::warning('Discord Bridge Heartbeat Failed: ' . $reason, [
            'headers' => $request->headers->all(),
            'evidence' => $evidence,
        ]);
        $this->orchestrator->record('bridge_signature_failure', [
            'severity' => 'high',
            'confidence' => 95,
            'source_ip' => $request->ip(),
            'summary' => $summary,
            'evidence' => $evidence + [
                'reason' => $reason,
            ],
            'blocked' => true,
            'verdict' => SecurityVocabulary::VERDICT_BLOCKED,
            'mitigation_stage' => SecurityVocabulary::STAGE_TEMP_BLOCK,
            'execute_actions' => $executeActions,
        ]);
    }

    private function cache(): CacheRepository
    {
        $cacheStore = config('security.cache_store');

        return $cacheStore ? Cache::store($cacheStore) : Cache::store();
    }

    private function discordStaffAllowed(array $messagePayload): bool
    {
        $requiredRoles = $this->settings->staffRoleIds();
        if ($requiredRoles === []) {
            return false;
        }

        $roles = array_values(array_filter(array_map(
            'strval',
            Arr::wrap(Arr::get($messagePayload, 'member.roles', Arr::get($messagePayload, 'author.roles', [])))
        )));

        return array_intersect($requiredRoles, $roles) !== [];
    }

    private function canAuthorAsStaff(Ticket $ticket, ?UserOAuthAccount $authorAccount, array $messagePayload): bool
    {
        if ($this->discordStaffAllowed($messagePayload)) {
            return true;
        }

        if (!$authorAccount?->user) {
            return false;
        }

        return $authorAccount->user->root_admin
            || ((int) $ticket->assigned_admin_id > 0 && (int) $authorAccount->user_id === (int) $ticket->assigned_admin_id);
    }

    private function syncRequesterDiscordIdentity(Ticket $ticket, ?UserOAuthAccount $authorAccount, string $authorDiscordId): void
    {
        if (!$authorAccount || (int) $authorAccount->user_id !== (int) $ticket->user_id) {
            return;
        }

        $displayName = trim((string) ($authorAccount->display_name ?? ''));
        $avatar = trim((string) ($authorAccount->avatar ?? ''));
        $updates = [];

        if ($authorDiscordId !== '' && $authorDiscordId !== (string) $ticket->requester_discord_user_id) {
            $updates['requester_discord_user_id'] = $authorDiscordId;
        }

        if ($displayName !== '' && $displayName !== (string) $ticket->requester_discord_name) {
            $updates['requester_discord_name'] = $displayName;
        }

        if ($avatar !== '' && $avatar !== (string) $ticket->requester_discord_avatar) {
            $updates['requester_discord_avatar'] = $avatar;
        }

        if ($updates === []) {
            return;
        }

        $ticket->forceFill($updates)->saveOrFail();
    }
}
