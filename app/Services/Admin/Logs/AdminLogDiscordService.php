<?php

namespace Pterodactyl\Services\Admin\Logs;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Ticket;
use Pterodactyl\Models\Node;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\ActivityLog;
use Pterodactyl\Models\BillingOrder;
use Pterodactyl\Models\BillingInvoice;
use Pterodactyl\Models\BillingPayment;
use Pterodactyl\Models\BillingGatewayEvent;
use Pterodactyl\Models\BillingPaymentAttempt;
use Pterodactyl\Services\Security\SecurityVocabulary;
use RuntimeException;

class AdminLogDiscordService
{
    private const API_BASE = 'https://discord.com/api/v10';

    public function __construct()
    {
    }

    public function relayActivity(ActivityLog $activity, string $category): void
    {
        $channelId = $this->channelId($category);
        if (!$channelId) {
            return;
        }

        if ($category === 'security') {
            $this->relaySecurityActivity($activity, $channelId);

            return;
        }

        $subject = optional($activity->subjects->first())->subject;
        $properties = $activity->properties?->toArray() ?? [];
        $context = $this->contextString($properties);

        $this->postPayload($channelId, [
            'content' => null,
            'embeds' => [[
                'title' => sprintf('%s %s', config('app.name', 'Panel'), $this->categoryTitle($category)),
                'description' => sprintf('`%s`', $activity->event),
                'color' => $this->activityColor($category, $activity->event),
                'fields' => $this->activityFields($category, $activity, $subject, $properties, $context),
                'timestamp' => optional($activity->timestamp)?->toIso8601String(),
            ]],
            'allowed_mentions' => ['parse' => []],
        ]);
    }

    private function relaySecurityActivity(ActivityLog $activity, string $channelId): void
    {
        $properties = $activity->properties?->toArray() ?? [];
        $subject = optional($activity->subjects->first())->subject;
        $attack = trim((string) ($properties['attack_name'] ?? ''));
        $outcome = trim((string) ($properties['outcome'] ?? ''));
        $protection = trim((string) ($properties['protection_status'] ?? ''));
        $statusLine = trim((string) ($properties['status_line'] ?? $activity->description ?? 'Security activity recorded.'));
        $summary = trim((string) ($properties['summary'] ?? ''));
        $evidence = trim((string) ($properties['evidence_summary'] ?? $this->contextString($properties)));
        $source = trim((string) ($properties['source_ip'] ?? $properties['ip'] ?? $activity->ip ?? 'Unknown'));
        $actor = trim((string) ($properties['actor_label'] ?? $this->actorLabel($activity->actor)));
        $target = trim((string) ($properties['target_label'] ?? $this->subjectLabel($subject)));
        $node = trim((string) ($properties['node_label'] ?? ''));
        $severity = trim((string) ($properties['severity'] ?? 'unknown'));
        $surface = trim((string) ($properties['surface'] ?? 'security'));
        $verdict = trim((string) ($properties['verdict'] ?? 'observed_only'));

        $this->postPayload($channelId, [
            'content' => null,
            'embeds' => [[
                'title' => sprintf('%s Security Alert', config('app.name', 'Panel')),
                'description' => $statusLine !== '' ? $statusLine : sprintf('`%s`', $activity->event),
                'color' => $this->securityColor($verdict),
                'fields' => array_values(array_filter([
                    ['name' => 'Attack', 'value' => $attack !== '' ? $attack : $activity->event, 'inline' => false],
                    ['name' => 'Outcome', 'value' => $outcome !== '' ? $outcome : 'Security Event', 'inline' => true],
                    ['name' => 'Protection', 'value' => $protection !== '' ? $protection : 'Unknown', 'inline' => true],
                    ['name' => 'Severity', 'value' => Str::headline($severity), 'inline' => true],
                    ['name' => 'Surface', 'value' => Str::headline($surface), 'inline' => true],
                    ['name' => 'Source IP', 'value' => $source !== '' ? $source : 'Unknown', 'inline' => true],
                    ['name' => 'Actor', 'value' => $actor !== '' ? $actor : 'System / Anonymous', 'inline' => true],
                    ['name' => 'Target', 'value' => $target !== '' ? $target : 'n/a', 'inline' => true],
                    $node !== '' ? ['name' => 'Node', 'value' => $node, 'inline' => true] : null,
                    $summary !== '' ? ['name' => 'Summary', 'value' => Str::limit($summary, 500, '...'), 'inline' => false] : null,
                    $evidence !== '' ? ['name' => 'Evidence', 'value' => Str::limit($evidence, 800, '...'), 'inline' => false] : null,
                    !empty($properties['threat_id']) ? ['name' => 'Threat ID', 'value' => (string) $properties['threat_id'], 'inline' => false] : null,
                ])),
                'timestamp' => optional($activity->timestamp)?->toIso8601String(),
            ]],
            'allowed_mentions' => ['parse' => []],
        ]);
    }

    public function relayPaymentModel(Model $model, string $action): void
    {
        $channelId = $this->channelId('payment');
        if (!$channelId) {
            return;
        }

        [$title, $fields, $color] = $this->paymentPayload($model, $action);
        if ($title === '') {
            return;
        }

        $this->postPayload($channelId, [
            'content' => null,
            'embeds' => [[
                'title' => sprintf('%s Payment Log', config('app.name', 'Panel')),
                'description' => $title,
                'color' => $color,
                'fields' => $fields,
                'timestamp' => optional($model->updated_at ?? $model->created_at)?->toIso8601String(),
            ]],
            'allowed_mentions' => ['parse' => []],
        ]);
    }

    private function paymentPayload(Model $model, string $action): array
    {
        return match (true) {
            $model instanceof BillingInvoice => [
                sprintf('Invoice `%s` %s', $model->invoice_number, $action),
                [
                    ['name' => 'User', 'value' => $model->user?->email ?? 'Unknown user', 'inline' => true],
                    ['name' => 'Status', 'value' => $model->status, 'inline' => true],
                    ['name' => 'Amount', 'value' => sprintf('%s %.2f', strtoupper($model->currency), (float) $model->grand_total), 'inline' => true],
                ],
                $this->paymentStatusColor($model->status),
            ],
            $model instanceof BillingPayment => [
                sprintf('Payment `%s` %s', $model->payment_number, $action),
                [
                    ['name' => 'Invoice', 'value' => $model->invoice?->invoice_number ?? 'n/a', 'inline' => true],
                    ['name' => 'User', 'value' => $model->invoice?->user?->email ?? 'Unknown user', 'inline' => true],
                    ['name' => 'Status', 'value' => $model->status, 'inline' => true],
                ],
                $this->paymentStatusColor($model->status),
            ],
            $model instanceof BillingPaymentAttempt => [
                sprintf('Payment attempt #%d %s', (int) $model->attempt_number, $action),
                [
                    ['name' => 'Invoice', 'value' => $model->invoice?->invoice_number ?? 'n/a', 'inline' => true],
                    ['name' => 'Status', 'value' => $model->status, 'inline' => true],
                    ['name' => 'Failure', 'value' => $model->failure_reason ?: 'None', 'inline' => false],
                ],
                $this->paymentStatusColor($model->status),
            ],
            $model instanceof BillingOrder => [
                sprintf('Provision order for `%s` %s', $model->server_name, $action),
                [
                    ['name' => 'User', 'value' => $model->user?->email ?? 'Unknown user', 'inline' => true],
                    ['name' => 'Type', 'value' => $model->order_type, 'inline' => true],
                    ['name' => 'Status', 'value' => $model->status, 'inline' => true],
                    ['name' => 'Failure', 'value' => $model->provision_failure_message ?: 'None', 'inline' => false],
                ],
                $this->paymentStatusColor($model->status),
            ],
            $model instanceof BillingGatewayEvent => [
                sprintf('Gateway event `%s` %s', $model->event_type, $action),
                [
                    ['name' => 'Provider', 'value' => $model->provider, 'inline' => true],
                    ['name' => 'Status', 'value' => $model->status, 'inline' => true],
                    ['name' => 'Transaction', 'value' => $model->provider_transaction_id ?: 'n/a', 'inline' => true],
                    ['name' => 'Error', 'value' => $model->processing_error ?: 'None', 'inline' => false],
                ],
                $this->paymentStatusColor($model->status),
            ],
            default => ['', [], 0x4C7CF0],
        };
    }

    private function activityFields(string $category, ActivityLog $activity, mixed $subject, array $properties, string $context): array
    {
        if ($category === 'new_account') {
            $user = $subject instanceof User ? $subject : null;
            $emailVerified = array_key_exists('email_verified', $properties)
                ? (bool) $properties['email_verified']
                : ($user?->is_email_verified ?? false);
            $signupStage = trim((string) ($properties['signup_stage'] ?? ''));

            return [
                ['name' => 'Username', 'value' => $this->newAccountUsername($properties, $user), 'inline' => true],
                ['name' => 'Email', 'value' => $this->newAccountEmail($properties, $user), 'inline' => true],
                ['name' => 'Name', 'value' => $this->newAccountName($properties, $user), 'inline' => true],
                ['name' => 'Verified', 'value' => $emailVerified ? 'Yes' : 'No', 'inline' => true],
                ['name' => 'Stage', 'value' => $signupStage !== '' ? $signupStage : 'n/a', 'inline' => true],
                ['name' => 'IP', 'value' => (string) ($properties['ip'] ?? $activity->ip ?? 'Unknown'), 'inline' => true],
                ['name' => 'Subject', 'value' => $this->subjectLabel($subject), 'inline' => false],
                ['name' => 'Context', 'value' => $context, 'inline' => false],
            ];
        }

        return [
            ['name' => 'Actor', 'value' => $this->actorLabel($activity->actor), 'inline' => true],
            ['name' => 'Subject', 'value' => $this->subjectLabel($subject), 'inline' => true],
            ['name' => 'IP', 'value' => (string) ($properties['ip'] ?? $activity->ip ?? 'Unknown'), 'inline' => true],
            ['name' => 'Context', 'value' => $context, 'inline' => false],
        ];
    }

    private function postPayload(string $channelId, array $payload): void
    {
        $response = $this->http()->post(sprintf('%s/channels/%s/messages', self::API_BASE, $channelId), $payload);

        if (!$response->successful()) {
            throw new RuntimeException((string) $response->body());
        }
    }

    private function http(): PendingRequest
    {
        $token = trim((string) config('services.discord.bot_token', ''));
        if ($token === '') {
            throw new RuntimeException('Discord bot token is not configured.');
        }

        return Http::acceptJson()
            ->withToken($token, 'Bot')
            ->timeout((int) config('pterodactyl.guzzle.timeout', 30))
            ->connectTimeout((int) config('pterodactyl.guzzle.connect_timeout', 10));
    }

    private function channelId(string $category): ?string
    {
        $channel = trim((string) config("admin_logs.{$category}.discord_channel_id", ''));

        return $channel !== '' ? $channel : null;
    }

    private function categoryTitle(string $category): string
    {
        return match ($category) {
            'new_account' => 'New Account Log',
            'payment' => 'Payment Log',
            'security' => 'Security Log',
            'login' => 'Login Log',
            'forgot_password' => 'Forget Password Log',
            'change_password' => 'Change Password Log',
            'change_email' => 'Change Email Log',
            'ticket' => 'Ticket Log',
            default => 'System Log',
        };
    }

    private function newAccountUsername(array $properties, ?User $user): string
    {
        $value = trim((string) ($properties['username'] ?? $user?->username ?? ''));

        return $value !== '' ? $value : 'Unknown username';
    }

    private function newAccountEmail(array $properties, ?User $user): string
    {
        $value = trim((string) ($properties['email'] ?? $user?->email ?? ''));

        return $value !== '' ? $value : 'Unknown email';
    }

    private function newAccountName(array $properties, ?User $user): string
    {
        $value = trim((string) ($properties['name'] ?? ''));
        if ($value !== '') {
            return $value;
        }

        if ($user) {
            $value = trim($user->name_first . ' ' . $user->name_last);
        }

        return $value !== '' ? $value : 'Unknown name';
    }

    private function actorLabel(mixed $actor): string
    {
        if ($actor instanceof User) {
            return sprintf('%s (%s)', $actor->username, $actor->email);
        }

        return 'System / Anonymous';
    }

    private function subjectLabel(mixed $subject): string
    {
        return match (true) {
            $subject instanceof User => sprintf('%s (%s)', $subject->username, $subject->email),
            $subject instanceof Ticket => $subject->ticket_number,
            $subject instanceof Server => sprintf('%s (%s)', $subject->name, $subject->uuidShort),
            $subject instanceof Node => sprintf('%s (%s)', $subject->name, $subject->getConnectionAddress()),
            $subject instanceof Model => class_basename($subject) . ' #' . $subject->getKey(),
            default => 'n/a',
        };
    }

    private function contextString(array $properties): string
    {
        unset($properties['ip'], $properties['useragent']);

        if ($properties === []) {
            return 'No extra context.';
        }

        $parts = [];
        foreach ($properties as $key => $value) {
            $parts[] = sprintf('%s: %s', $key, is_scalar($value) || is_null($value)
                ? (string) $value
                : json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }

        return Str::limit(implode(' | ', $parts), 900, '...');
    }

    private function activityColor(string $category, string $event): int
    {
        if (str_contains($event, 'fail') || str_contains($event, 'locked') || $category === 'security') {
            return 0xE45757;
        }

        if (str_contains($event, 'success') || str_contains($event, 'signup') || str_contains($event, 'verified')) {
            return 0x6CC24A;
        }

        return 0x4C7CF0;
    }

    private function securityColor(string $verdict): int
    {
        return match ($verdict) {
            SecurityVocabulary::VERDICT_BLOCKED,
            SecurityVocabulary::VERDICT_CONTAINED,
            SecurityVocabulary::VERDICT_QUARANTINED => 0x6CC24A,
            SecurityVocabulary::VERDICT_CHALLENGED,
            SecurityVocabulary::VERDICT_RATE_LIMITED,
            SecurityVocabulary::VERDICT_NOT_CONTROLLABLE => 0xE0A43A,
            SecurityVocabulary::VERDICT_FAILED_TO_BLOCK => 0xE45757,
            default => 0x4C7CF0,
        };
    }

    private function paymentStatusColor(string $status): int
    {
        return match ($status) {
            'paid', 'verified_paid', 'processed', 'provisioned' => 0x6CC24A,
            'failed', 'verified_failed', 'provision_failed', 'refund_failed' => 0xE45757,
            default => 0xE0A43A,
        };
    }
}
