<?php

namespace Pterodactyl\Services\Tickets;

use Pterodactyl\Models\Ticket;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\BillingPayment;
use Pterodactyl\Models\BillingInvoice;
use Pterodactyl\Models\UserOAuthAccount;

class TicketDiscordInteractionService
{
    public function __construct(
        private TicketEligibilityService $eligibility,
        private TicketService $tickets,
        private TicketDiscordService $discord,
        private TicketSettingsService $settings,
        private TicketUrlService $urls,
    ) {
    }

    public function handle(array $payload): array
    {
        try {
            $type = (int) ($payload['type'] ?? 0);

            if ($type === 1) {
                return ['type' => 1];
            }

            $customId = (string) ($payload['data']['custom_id'] ?? '');
            if ($customId === 'tickets:launcher:reset') {
                return $this->launcherReset();
            }

            $discordUserId = (string) ($payload['member']['user']['id'] ?? $payload['user']['id'] ?? '');
            $account = $discordUserId !== ''
                ? UserOAuthAccount::query()
                    ->with('user')
                    ->where('provider', 'discord')
                    ->where('provider_id', $discordUserId)
                    ->first()
                : null;

            if ($customId === 'tickets:thread:close') {
                return $this->closeThreadTicket($payload, $account);
            }

            if (!$account?->user) {
                return $this->linkRequiredUpdate();
            }

            $user = $account->user;

            if ($customId === 'tickets:open:type') {
                $selected = (string) (($payload['data']['values'][0] ?? '') ?: '');

                return match ($selected) {
                    Ticket::CATEGORY_PAYMENT => $this->selectResponse(
                        'Choose the invoice you need help paying.',
                        'tickets:select:payment',
                        $this->eligibility->paymentEligibles($user),
                        'invoice_number',
                        $discordUserId
                    ),
                    Ticket::CATEGORY_REFUND => $this->selectResponse(
                        'Choose the payment you want refunded.',
                        'tickets:select:refund',
                        $this->eligibility->refundEligibles($user),
                        'payment_number',
                        $discordUserId
                    ),
                    Ticket::CATEGORY_SUPPORT => $this->selectResponse(
                        'Choose the server ID related to this support request.',
                        'tickets:select:support-server',
                        $this->eligibility->supportServerEligibles($user),
                        'server_label',
                        $discordUserId
                    ),
                    default => $this->launcherReset(),
                };
            }

            if (
                str_starts_with($customId, 'tickets:select:payment:')
                || str_starts_with($customId, 'tickets:select:refund:')
                || str_starts_with($customId, 'tickets:select:support-server:')
            ) {
                if (!$this->customIdBelongsToUser($customId, $discordUserId)) {
                    return $this->launcherReset();
                }

                $value = (string) (($payload['data']['values'][0] ?? '') ?: '');
                if ($value === '') {
                    return $this->launcherReset();
                }

                if (str_starts_with($customId, 'tickets:select:payment:')) {
                    $invoice = BillingInvoice::query()->where('id', (int) $value)->where('user_id', $user->id)->first();
                    if (!$invoice) {
                        return $this->launcherNoticeUpdate('That invoice is no longer available. Start again from the main ticket menu.');
                    }

                    $ticket = $this->tickets->create($user, [
                        'category' => Ticket::CATEGORY_PAYMENT,
                        'subject' => sprintf('Payment help for invoice %s', $invoice->invoice_number),
                        'billing_invoice_id' => $invoice->id,
                        'billing_order_id' => $invoice->billing_order_id,
                        'billing_subscription_id' => $invoice->subscription_id,
                        'body' => sprintf('Opened from Discord launcher for invoice %s.', $invoice->invoice_number),
                    ], [
                        'source' => Ticket::SOURCE_DISCORD,
                        'status' => Ticket::STATUS_WAITING_FOR_STAFF,
                    ]);

                    return $this->prepareAndRespond($ticket);
                }

                if (str_starts_with($customId, 'tickets:select:support-server:')) {
                    $serverId = (int) $value;
                    $server = $serverId > 0
                        ? $user->accessibleServers()->where('servers.id', $serverId)->first()
                        : null;

                    if ($serverId > 0 && !$server instanceof Server) {
                        return $this->launcherNoticeUpdate('That server is no longer available. Start again from the main ticket menu.');
                    }

                    return [
                        'type' => 9,
                        'data' => [
                            'custom_id' => sprintf('tickets:modal:support:%s:%d', $discordUserId, $server?->id ?? 0),
                            'title' => $server ? 'Support for ' . mb_strimwidth($server->name, 0, 25, '') : 'General Support',
                            'components' => [[
                                'type' => 1,
                                'components' => [[
                                    'type' => 4,
                                    'custom_id' => 'body',
                                    'label' => 'Message',
                                    'style' => 2,
                                    'min_length' => 4,
                                    'max_length' => 1000,
                                    'required' => true,
                                    'placeholder' => $server
                                        ? sprintf('Describe the issue for server #%d.', $server->id)
                                        : 'Describe the issue you need help with.',
                                ]],
                            ]],
                        ],
                    ];
                }

                $payment = BillingPayment::query()
                    ->where('id', (int) $value)
                    ->whereHas('invoice', fn ($query) => $query->where('user_id', $user->id))
                    ->first();

                if (!$payment) {
                    return $this->launcherNoticeUpdate('That payment is no longer available. Start again from the main ticket menu.');
                }

                $ticket = $this->tickets->create($user, [
                    'category' => Ticket::CATEGORY_REFUND,
                    'subject' => sprintf('Refund request for payment %s', $payment->payment_number),
                    'billing_payment_id' => $payment->id,
                    'billing_invoice_id' => $payment->invoice_id,
                    'billing_order_id' => $payment->invoice?->billing_order_id,
                    'billing_subscription_id' => $payment->invoice?->subscription_id,
                    'body' => sprintf('Opened from Discord launcher for payment %s.', $payment->payment_number),
                ], [
                    'source' => Ticket::SOURCE_DISCORD,
                    'status' => Ticket::STATUS_WAITING_FOR_STAFF,
                ]);

                return $this->prepareAndRespond($ticket);
            }

            if ($type === 5 && str_starts_with($customId, 'tickets:modal:support:')) {
                [$ownerDiscordId, $serverId] = $this->parseSupportModalCustomId($customId);
                if ($ownerDiscordId !== $discordUserId) {
                    return $this->launcherReset();
                }

                $fields = collect($payload['data']['components'] ?? [])
                    ->flatMap(fn ($row) => $row['components'] ?? [])
                    ->mapWithKeys(fn ($component) => [$component['custom_id'] => $component['value'] ?? null]);

                $server = $serverId > 0
                    ? $user->accessibleServers()->where('servers.id', $serverId)->first()
                    : null;

                if ($serverId > 0 && !$server instanceof Server) {
                    return $this->launcherReset();
                }

                $ticket = $this->tickets->create($user, [
                    'category' => Ticket::CATEGORY_SUPPORT,
                    'subject' => $server instanceof Server
                        ? sprintf('Support request for %s', $server->name)
                        : 'General support request',
                    'body' => (string) ($fields['body'] ?? ''),
                    'meta' => array_filter([
                        'support_server_id' => $server?->id,
                        'support_server_name' => $server?->name,
                        'support_server_uuid_short' => $server?->uuidShort,
                    ], fn ($value) => !is_null($value) && $value !== ''),
                ], [
                    'source' => Ticket::SOURCE_DISCORD,
                    'status' => Ticket::STATUS_WAITING_FOR_STAFF,
                ]);

                return $this->prepareAndRespond($ticket);
            }

            return $this->launcherReset();
        } catch (\Throwable $exception) {
            report($exception);

            return $this->launcherNoticeUpdate(
                'The support request could not be completed in Discord right now. Start again from the launcher or open the support center in the panel.',
                [[
                    'type' => 2,
                    'style' => 5,
                    'label' => 'Open Support Center',
                    'url' => $this->urls->composeUrl(Ticket::CATEGORY_SUPPORT),
                ]]
            );
        }
    }

    private function closeThreadTicket(array $payload, ?UserOAuthAccount $account): array
    {
        if (!$this->isDiscordStaff($account, $payload)) {
            return $this->ephemeral('Only Discord staff roles or linked admins can close tickets from Discord.');
        }

        $threadId = (string) ($payload['channel_id'] ?? '');
        if ($threadId === '') {
            return ['type' => 6];
        }

        $ticket = Ticket::query()->where('discord_thread_id', $threadId)->first();
        if (!$ticket) {
            return ['type' => 6];
        }

        $actorUserId = $account?->user?->id ?: null;
        $actorName = $account?->user?->username
            ?: (string) ($payload['member']['user']['username'] ?? $payload['user']['username'] ?? 'Discord staff');

        $ticket = $this->tickets->updateStatus($ticket, Ticket::STATUS_CLOSED, $actorUserId);

        try {
            $this->discord->closeTicketThread($ticket, sprintf('Ticket %s closed by %s from Discord', $ticket->ticket_number, $actorName));
        } catch (\Throwable $exception) {
            report($exception);

            return $this->ephemeral('The ticket was closed in the panel, but Discord could not delete the thread automatically.');
        }

        return [
            'type' => 6,
        ];
    }

    private function prepareAndRespond(Ticket $ticket): array
    {
        $this->discord->scheduleTicketSyncAfterResponse($ticket->id);

        return $this->launcherReset();
    }

    private function launcherReset(): array
    {
        return [
            'type' => 7,
            'data' => $this->discord->launcherPayload(),
        ];
    }

    private function linkRequiredUpdate(): array
    {
        return $this->launcherNoticeUpdate(
            'Link your Discord account in the panel first, then use this launcher again.',
            [[
                'type' => 2,
                'style' => 5,
                'label' => 'Link Discord',
                'url' => route('auth.oauth.redirect', [
                    'provider' => 'discord',
                    'intent' => 'link',
                    'return_to' => $this->urls->composeUrl(Ticket::CATEGORY_SUPPORT),
                ]),
            ]],
            'Linked Discord account required before a ticket can be opened.'
        );
    }

    private function launcherNoticeUpdate(string $description, array $buttons = [], ?string $footer = null): array
    {
        $buttons[] = [
            'type' => 2,
            'style' => 2,
            'label' => 'Back to Main Menu',
            'custom_id' => 'tickets:launcher:reset',
        ];

        return [
            'type' => 7,
            'data' => $this->discord->launcherStepPayload(
                $description,
                [[
                    'type' => 1,
                    'components' => array_slice($buttons, 0, 5),
                ]],
                $footer
            ),
        ];
    }

    private function ephemeral(string $content, array $components = []): array
    {
        $data = [
            'content' => $content,
            'flags' => 64,
        ];

        if ($components !== []) {
            $data['components'] = [[
                'type' => 1,
                'components' => $components,
            ]];
        }

        return [
            'type' => 4,
            'data' => $data,
        ];
    }

    private function selectResponse(
        string $placeholder,
        string $customId,
        array $items,
        string $labelKey,
        string $ownerDiscordId
    ): array {
        if ($items === []) {
            return $this->launcherNoticeUpdate('There is nothing eligible to open for this category right now.');
        }

        return [
            'type' => 7,
            'data' => $this->discord->launcherStepPayload(
                $placeholder,
                [
                    [
                        'type' => 1,
                        'components' => [[
                            'type' => 3,
                            'custom_id' => sprintf('%s:%s', $customId, $ownerDiscordId),
                            'placeholder' => $placeholder,
                            'options' => array_map(fn ($item) => [
                                'label' => (string) ($item[$labelKey] ?? $item['subject'] ?? 'Item'),
                                'value' => (string) ($item['invoice_id'] ?? $item['payment_id'] ?? $item['server_id'] ?? ''),
                                'description' => isset($item['server_id'])
                                    ? ($item['server_id'] > 0
                                        ? sprintf('Server ID #%d%s', (int) $item['server_id'], !empty($item['server_uuid_short']) ? ' • ' . $item['server_uuid_short'] : '')
                                        : 'General support request')
                                    : sprintf('%s %.2f', $item['currency'] ?? 'MYR', (float) ($item['amount'] ?? 0)),
                            ], array_slice($items, 0, 25)),
                        ]],
                    ],
                    [
                        'type' => 1,
                        'components' => [
                            [
                                'type' => 2,
                                'style' => 2,
                                'label' => 'Back to Main Menu',
                                'custom_id' => 'tickets:launcher:reset',
                            ],
                            [
                                'type' => 2,
                                'style' => 5,
                                'label' => 'Open Support Center',
                                'url' => $this->urls->composeUrl(Ticket::CATEGORY_SUPPORT),
                            ],
                        ],
                    ],
                ]
            ),
        ];
    }

    private function customIdBelongsToUser(string $customId, string $discordUserId): bool
    {
        $parts = explode(':', $customId);

        return (string) end($parts) === $discordUserId;
    }

    private function parseSupportModalCustomId(string $customId): array
    {
        $parts = explode(':', $customId);

        return [
            (string) ($parts[3] ?? ''),
            (int) ($parts[4] ?? 0),
        ];
    }

    private function isDiscordStaff(?UserOAuthAccount $account, array $payload): bool
    {
        $user = $account?->user;

        $requiredRoles = $this->settings->staffRoleIds();
        if ($requiredRoles === []) {
            return (bool) $user?->root_admin;
        }

        $roles = array_values(array_filter(array_map(
            'strval',
            array_merge(
                (array) ($payload['member']['roles'] ?? []),
                (array) ($payload['user']['roles'] ?? [])
            )
        )));

        return (bool) ($user?->root_admin || array_intersect($requiredRoles, $roles) !== []);
    }
}
