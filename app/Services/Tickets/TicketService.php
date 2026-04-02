<?php

namespace Pterodactyl\Services\Tickets;

use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Ticket;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\BillingPayment;
use Pterodactyl\Models\BillingInvoice;
use Pterodactyl\Models\BillingSubscription;
use Pterodactyl\Models\UserOAuthAccount;
use Pterodactyl\Exceptions\DisplayException;

class TicketService
{
    public function __construct(
        private TicketEligibilityService $eligibilityService,
        private TicketMessageService $messageService,
    ) {
    }

    public function create(User $user, array $payload, array $options = []): Ticket
    {
        $category = (string) ($payload['category'] ?? Ticket::CATEGORY_SUPPORT);
        $invoice = isset($payload['billing_invoice_id']) ? BillingInvoice::query()->find($payload['billing_invoice_id']) : null;
        $payment = isset($payload['billing_payment_id']) ? BillingPayment::query()->find($payload['billing_payment_id']) : null;
        $subscription = isset($payload['billing_subscription_id']) ? BillingSubscription::query()->find($payload['billing_subscription_id']) : null;
        $supportServerId = max((int) ($payload['support_server_id'] ?? 0), 0);
        $supportServer = null;
        $existing = $this->resolveExistingOpenTicket($category, $invoice, $payment);

        if ($category === Ticket::CATEGORY_SUPPORT && $supportServerId > 0) {
            $supportServer = $user->accessibleServers()->where('servers.id', $supportServerId)->first();
            if (!$supportServer instanceof Server) {
                throw new DisplayException('The selected support server is not available anymore.');
            }
        }

        if ($existing) {
            $body = trim((string) ($payload['body'] ?? ''));
            $attachments = Arr::wrap($options['attachments'] ?? []);
            if ($body !== '' || $attachments !== []) {
                $author = ($options['author'] ?? null) instanceof User ? $options['author'] : $user;
                $this->messageService->postUserMessage(
                    $existing,
                    $author,
                    $body,
                    $attachments
                );
            }

            return $existing->fresh(['messages.attachments', 'invoice', 'payment', 'subscription', 'order', 'assignedAdmin']);
        }

        $discordAccount = $user->loadMissing('oauthAccounts')->oauthAccounts->first(
            fn (UserOAuthAccount $account) => $account->provider === 'discord'
        );

        return DB::transaction(function () use ($user, $payload, $options, $category, $invoice, $payment, $subscription, $discordAccount, $supportServer) {
            $meta = array_filter(array_merge(
                Arr::wrap($payload['meta'] ?? []),
                [
                    'checkout_context' => Arr::get($options, 'checkout_context'),
                    'created_via' => Arr::get($options, 'created_via'),
                    'support_server_id' => $supportServer?->id,
                    'support_server_name' => $supportServer?->name,
                    'support_server_uuid_short' => $supportServer?->uuidShort,
                ]
            ), fn ($value) => !is_null($value));

            $ticket = Ticket::query()->create([
                'ticket_number' => $this->nextTicketNumber(),
                'user_id' => $user->id,
                'category' => $category,
                'source' => (string) ($options['source'] ?? $payload['source'] ?? Ticket::SOURCE_CONSOLE),
                'status' => (string) ($options['status'] ?? $payload['status'] ?? Ticket::STATUS_WAITING_FOR_STAFF),
                'subject' => trim((string) ($payload['subject'] ?? 'Support request')),
                'billing_order_id' => $payload['billing_order_id'] ?? $invoice?->billing_order_id ?? $payment?->invoice?->billing_order_id,
                'billing_invoice_id' => $invoice?->id,
                'billing_payment_id' => $payment?->id,
                'billing_subscription_id' => $payload['billing_subscription_id'] ?? $invoice?->subscription_id ?? $payment?->invoice?->subscription_id ?? $subscription?->id,
                'requester_discord_user_id' => $discordAccount?->provider_id,
                'requester_discord_name' => $discordAccount?->display_name,
                'requester_discord_avatar' => $discordAccount?->avatar,
                'discord_sync_status' => (string) ($options['discord_sync_status'] ?? Ticket::DISCORD_SYNC_PENDING),
                'meta' => $meta,
            ]);

            $body = trim((string) ($payload['body'] ?? ''));
            $attachments = Arr::wrap($options['attachments'] ?? []);
            if ($body !== '' || $attachments !== []) {
                $author = ($options['author'] ?? null) instanceof User ? $options['author'] : $user;
                $this->messageService->postUserMessage(
                    $ticket,
                    $author,
                    $body,
                    $attachments
                );
            }

            return $ticket->fresh(['messages.attachments', 'invoice', 'payment', 'subscription', 'order', 'assignedAdmin']);
        });
    }

    public function postSystemMessage(Ticket $ticket, string $body, array $meta = []): Ticket
    {
        $this->messageService->postSystemMessage($ticket, $body, $meta);

        return $ticket->fresh(['messages.attachments', 'invoice', 'payment', 'subscription', 'order', 'assignedAdmin']);
    }

    public function markRead(Ticket $ticket, User $user): Ticket
    {
        $ticket->forceFill([
            $user->root_admin ? 'staff_last_read_at' : 'user_last_read_at' => CarbonImmutable::now(),
        ])->saveOrFail();

        return $ticket->fresh(['messages.attachments', 'invoice', 'payment', 'subscription', 'order', 'assignedAdmin']);
    }

    public function reopen(Ticket $ticket, User $user): Ticket
    {
        if ($ticket->status === Ticket::STATUS_CLOSED && !$user->root_admin) {
            throw new DisplayException('Only an administrator can reopen a closed ticket.');
        }

        $status = $user->root_admin ? Ticket::STATUS_WAITING_FOR_USER : Ticket::STATUS_WAITING_FOR_STAFF;

        $ticket->forceFill([
            'status' => $status,
            'resolved_at' => null,
            'closed_at' => null,
        ])->saveOrFail();

        return $ticket->fresh(['messages.attachments', 'invoice', 'payment', 'subscription', 'order', 'assignedAdmin']);
    }

    public function updateStatus(Ticket $ticket, string $status, ?int $assignedAdminId = null): Ticket
    {
        $payload = ['status' => $status];
        if ($status === Ticket::STATUS_RESOLVED) {
            $payload['resolved_at'] = CarbonImmutable::now();
            $payload['closed_at'] = null;
        } elseif ($status === Ticket::STATUS_CLOSED) {
            $payload['closed_at'] = CarbonImmutable::now();
        } else {
            $payload['resolved_at'] = null;
            $payload['closed_at'] = null;
        }

        if (!is_null($assignedAdminId)) {
            $payload['assigned_admin_id'] = $assignedAdminId;
        }

        $ticket->forceFill($payload)->saveOrFail();

        return $ticket->fresh(['messages.attachments', 'invoice', 'payment', 'subscription', 'order', 'assignedAdmin']);
    }

    public function resolveExistingOpenTicket(string $category, ?BillingInvoice $invoice = null, ?BillingPayment $payment = null): ?Ticket
    {
        return match ($category) {
            Ticket::CATEGORY_PAYMENT => $invoice ? $this->eligibilityService->activePaymentTicketForInvoice($invoice) : null,
            Ticket::CATEGORY_REFUND => $payment ? $this->eligibilityService->activeRefundTicketForPayment($payment) : null,
            default => null,
        };
    }

    private function nextTicketNumber(): string
    {
        do {
            $number = 'TKT-' . strtoupper(Str::random(8));
        } while (Ticket::query()->where('ticket_number', $number)->exists());

        return $number;
    }
}
