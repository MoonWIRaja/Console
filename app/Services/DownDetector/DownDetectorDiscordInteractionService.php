<?php

namespace Pterodactyl\Services\DownDetector;

use Carbon\CarbonImmutable;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\User;
use Pterodactyl\Models\UserOAuthAccount;

class DownDetectorDiscordInteractionService
{
    public function __construct(private DownDetectorRunnerService $runner)
    {
    }

    public function handle(array $payload): array
    {
        $customId = (string) data_get($payload, 'data.custom_id', '');
        $discordUserId = (string) data_get($payload, 'member.user.id', data_get($payload, 'user.id', ''));

        $account = $discordUserId !== ''
            ? UserOAuthAccount::query()
                ->with('user')
                ->where('provider', 'discord')
                ->where('provider_id', $discordUserId)
                ->first()
            : null;

        if ($customId === 'down-detector:server:launcher') {
            if (!$account?->user) {
                return $this->linkRequiredResponse();
            }

            return $this->selectorReply($account->user, $discordUserId, false);
        }

        if (str_starts_with($customId, 'down-detector:server:back:')) {
            if (!$account?->user || !$this->customIdBelongsToUser($customId, $discordUserId)) {
                return $this->linkRequiredResponse();
            }

            return $this->selectorReply($account->user, $discordUserId, true);
        }

        if (str_starts_with($customId, 'down-detector:server:select:')) {
            if (!$account?->user || !$this->customIdBelongsToUser($customId, $discordUserId)) {
                return $this->linkRequiredResponse();
            }

            $serverId = (int) data_get($payload, 'data.values.0', 0);
            $server = $serverId > 0
                ? $account->user->accessibleServers()
                    ->with(['node', 'allocation', 'transfer'])
                    ->where('servers.id', $serverId)
                    ->first()
                : null;

            if (!$server instanceof Server) {
                return $this->noticeUpdate(
                    'That server is no longer available. Choose another server from the list.',
                    $discordUserId
                );
            }

            return $this->serverStatusUpdate($server, $discordUserId);
        }

        return $this->ephemeral(
            'Unsupported down detector action.',
            [],
            15000
        );
    }

    private function selectorReply(User $user, string $discordUserId, bool $update): array
    {
        $servers = $user->accessibleServers()
            ->with(['node', 'allocation'])
            ->orderBy('servers.name')
            ->limit(25)
            ->get();

        if ($servers->isEmpty()) {
            $message = 'You do not have any accessible servers linked to this Discord account right now.';

            return $update
                ? $this->noticeUpdate($message, $discordUserId)
                : $this->ephemeral($message, [[
                    'type' => 2,
                    'style' => 5,
                    'label' => 'Open Panel',
                    'url' => route('account'),
                ]], 30000);
        }

        $data = [
            'flags' => 64,
            'embeds' => [[
                'title' => 'Choose a Server',
                'description' => "Pick one server to run a live health check.\n\nOnly you can see this menu.",
                'color' => 0x6CC24A,
                'footer' => [
                    'text' => 'This panel hides automatically after about one minute.',
                ],
            ]],
            'components' => [
                [
                    'type' => 1,
                    'components' => [[
                        'type' => 3,
                        'custom_id' => sprintf('down-detector:server:select:%s', $discordUserId),
                        'placeholder' => 'Select one of your servers',
                        'min_values' => 1,
                        'max_values' => 1,
                        'options' => $servers->map(fn (Server $server) => [
                            'label' => mb_strimwidth($server->name, 0, 100, ''),
                            'value' => (string) $server->id,
                            'description' => trim(sprintf(
                                '#%d%s%s',
                                $server->id,
                                $server->uuidShort ? ' • ' . $server->uuidShort : '',
                                $server->allocation?->port ? ' • Port ' . $server->allocation->port : ''
                            )),
                        ])->all(),
                    ]],
                ],
                [
                    'type' => 1,
                    'components' => [[
                        'type' => 2,
                        'style' => 5,
                        'label' => 'Open Panel',
                        'url' => route('account'),
                    ]],
                ],
            ],
        ];

        return [
            'type' => $update ? 7 : 4,
            'data' => $data,
            'actions' => [[
                'type' => 'delete_reply_after_ms',
                'delay_ms' => 60000,
            ]],
        ];
    }

    private function serverStatusUpdate(Server $server, string $discordUserId): array
    {
        $observation = $this->runner->inspectServer($server);
        $allocationHost = trim((string) ($server->allocation?->alias ?: $server->allocation?->ip ?: 'n/a'));
        $allocationPort = (int) ($server->allocation?->port ?? 0);
        $status = $this->statusPresentation($observation);

        return [
            'type' => 7,
            'data' => [
                'flags' => 64,
                'embeds' => [[
                    'title' => sprintf('Server Health: %s', mb_strimwidth($server->name, 0, 200, '')),
                    'description' => $status['description'],
                    'color' => $status['color'],
                    'fields' => [
                        [
                            'name' => 'Status',
                            'value' => $status['label'],
                            'inline' => true,
                        ],
                        [
                            'name' => 'Server',
                            'value' => sprintf('#%d • `%s`', $server->id, $server->uuidShort),
                            'inline' => true,
                        ],
                        [
                            'name' => 'Node',
                            'value' => sprintf('%s (#%d)', $server->node->name, $server->node->id),
                            'inline' => true,
                        ],
                        [
                            'name' => 'Allocation',
                            'value' => $allocationPort > 0
                                ? sprintf('`%s:%d`', $allocationHost, $allocationPort)
                                : '`Unavailable`',
                            'inline' => false,
                        ],
                        [
                            'name' => 'Probe Result',
                            'value' => mb_strimwidth((string) ($observation['message'] ?? 'No message returned.'), 0, 1000, ''),
                            'inline' => false,
                        ],
                        [
                            'name' => 'Checked At',
                            'value' => CarbonImmutable::now()->format('Y-m-d H:i:s T'),
                            'inline' => false,
                        ],
                    ],
                    'footer' => [
                        'text' => 'Only visible to you. This result hides automatically after about one minute.',
                    ],
                ]],
                'components' => [[
                    'type' => 1,
                    'components' => [
                        [
                            'type' => 2,
                            'style' => 2,
                            'label' => 'Check Another Server',
                            'custom_id' => sprintf('down-detector:server:back:%s', $discordUserId),
                        ],
                        [
                            'type' => 2,
                            'style' => 5,
                            'label' => 'Open Server in Panel',
                            'url' => url('/server/' . $server->uuidShort),
                        ],
                    ],
                ]],
            ],
            'actions' => [[
                'type' => 'delete_reply_after_ms',
                'delay_ms' => 60000,
            ]],
        ];
    }

    private function linkRequiredResponse(): array
    {
        return $this->ephemeral(
            'Link your Discord account in the panel first, then try the server health launcher again.',
            [[
                'type' => 2,
                'style' => 5,
                'label' => 'Link Discord',
                'url' => route('auth.oauth.redirect', [
                    'provider' => 'discord',
                    'intent' => 'link',
                    'return_to' => route('account'),
                ]),
            ]],
            30000
        );
    }

    private function noticeUpdate(string $message, string $discordUserId): array
    {
        return [
            'type' => 7,
            'data' => [
                'flags' => 64,
                'embeds' => [[
                    'title' => 'Server Health',
                    'description' => $message,
                    'color' => 0xF0AD4E,
                    'footer' => [
                        'text' => 'Only visible to you.',
                    ],
                ]],
                'components' => [[
                    'type' => 1,
                    'components' => [[
                        'type' => 2,
                        'style' => 2,
                        'label' => 'Back to Server List',
                        'custom_id' => sprintf('down-detector:server:back:%s', $discordUserId),
                    ]],
                ]],
            ],
            'actions' => [[
                'type' => 'delete_reply_after_ms',
                'delay_ms' => 60000,
            ]],
        ];
    }

    private function ephemeral(string $content, array $buttons = [], int $deleteAfterMs = 0): array
    {
        $data = [
            'content' => $content,
            'flags' => 64,
        ];

        if ($buttons !== []) {
            $data['components'] = [[
                'type' => 1,
                'components' => array_slice($buttons, 0, 5),
            ]];
        }

        $response = [
            'type' => 4,
            'data' => $data,
        ];

        if ($deleteAfterMs > 0) {
            $response['actions'] = [[
                'type' => 'delete_reply_after_ms',
                'delay_ms' => $deleteAfterMs,
            ]];
        }

        return $response;
    }

    private function customIdBelongsToUser(string $customId, string $discordUserId): bool
    {
        $parts = explode(':', $customId);

        return (string) end($parts) === $discordUserId;
    }

    private function statusPresentation(array $observation): array
    {
        $status = (string) ($observation['status'] ?? 'unknown');
        $reason = (string) ($observation['reason'] ?? '');
        $message = (string) ($observation['message'] ?? '');

        if ($status === 'up') {
            return [
                'label' => 'Healthy',
                'description' => 'Wings and the primary allocation are responding normally.',
                'color' => 0x57F287,
            ];
        }

        if ($status === 'ignored') {
            return match ($reason) {
                'starting' => [
                    'label' => 'Starting',
                    'description' => 'The server is still starting. Wait a little longer before treating it as down.',
                    'color' => 0xFEE75C,
                ],
                'installing' => [
                    'label' => 'Installing',
                    'description' => 'The server is installing or restoring and is not expected to be reachable yet.',
                    'color' => 0xFEE75C,
                ],
                'suspended' => [
                    'label' => 'Suspended',
                    'description' => 'The server is suspended and will not accept connections until it is unsuspended.',
                    'color' => 0xED4245,
                ],
                'transferring' => [
                    'label' => 'Transferring',
                    'description' => 'The server is currently transferring between nodes.',
                    'color' => 0xFEE75C,
                ],
                'node_maintenance' => [
                    'label' => 'Node Maintenance',
                    'description' => 'The node is under maintenance, so this server is intentionally ignored for outage checks.',
                    'color' => 0xFEE75C,
                ],
                default => [
                    'label' => 'Offline',
                    'description' => $message !== '' ? $message : 'The server is not currently expected to be running.',
                    'color' => 0xFEE75C,
                ],
            };
        }

        return match ($reason) {
            'daemon_state_failed' => [
                'label' => str_contains(strtolower($message), 'offline') ? 'Offline' : 'Daemon State Failed',
                'description' => $message !== '' ? $message : 'Wings did not report the server as running.',
                'color' => 0xED4245,
            ],
            'port_probe_failed' => [
                'label' => 'Port Unreachable',
                'description' => 'Wings reported the server, but the primary allocation did not accept a TCP connection.',
                'color' => 0xED4245,
            ],
            default => [
                'label' => 'Unreachable',
                'description' => $message !== '' ? $message : 'The panel could not reach Wings or the server allocation.',
                'color' => 0xED4245,
            ],
        };
    }
}
