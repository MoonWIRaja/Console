<?php

namespace Pterodactyl\Http\Controllers\Admin\Logs;

use Throwable;
use Illuminate\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Http\RedirectResponse;
use Illuminate\Contracts\Console\Kernel;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Ticket;
use Pterodactyl\Models\AuditLog;
use Pterodactyl\Models\ActivityLog;
use Pterodactyl\Models\BillingOrder;
use Pterodactyl\Models\TicketMessage;
use Pterodactyl\Models\BillingInvoice;
use Pterodactyl\Models\BillingPayment;
use Pterodactyl\Models\Security\SecurityEvent;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\BillingGatewayEvent;
use Pterodactyl\Models\BillingPaymentAttempt;
use Pterodactyl\Http\Requests\Admin\Logs\UpdateAdminLogSettingsRequest;
use Pterodactyl\Services\Admin\Settings\AdminSettingsStoreService;
use Pterodactyl\Services\Security\SecurityEventFormatterService;

class LogCenterController extends Controller
{
    private const TABS = [
        'new-account' => 'New Account Logs',
        'payment' => 'Payment Logs',
        'security' => 'Security Logs',
        'login' => 'Login Logs',
        'forgot-password' => 'Forget Password Logs',
        'change-password' => 'Change Password Logs',
        'change-email' => 'Change Email Logs',
        'ticket' => 'Ticket Logs',
    ];

    public function __construct(
        private AlertsMessageBag $alert,
        private Kernel $kernel,
        private AdminSettingsStoreService $settings,
        private SecurityEventFormatterService $securityFormatter,
    ) {
    }

    public function index(Request $request): View
    {
        $activeTab = array_key_exists((string) $request->query('tab', 'new-account'), self::TABS)
            ? (string) $request->query('tab', 'new-account')
            : 'new-account';

        return view('admin.logs.index', [
            'activeTab' => $activeTab,
            'tabs' => self::TABS,
            'channelSettings' => [
                'admin_logs:new_account:discord_channel_id' => config('admin_logs.new_account.discord_channel_id'),
                'admin_logs:payment:discord_channel_id' => config('admin_logs.payment.discord_channel_id'),
                'admin_logs:security:discord_channel_id' => config('admin_logs.security.discord_channel_id'),
                'admin_logs:login:discord_channel_id' => config('admin_logs.login.discord_channel_id'),
                'admin_logs:forgot_password:discord_channel_id' => config('admin_logs.forgot_password.discord_channel_id'),
                'admin_logs:change_password:discord_channel_id' => config('admin_logs.change_password.discord_channel_id'),
                'admin_logs:change_email:discord_channel_id' => config('admin_logs.change_email.discord_channel_id'),
                'admin_logs:ticket:discord_channel_id' => config('admin_logs.ticket.discord_channel_id'),
            ],
            'payload' => match ($activeTab) {
                'new-account' => ['rows' => $this->newAccountRows()],
                'payment' => $this->paymentRows(),
                'security' => ['rows' => $this->securityRows()],
                'login' => ['rows' => $this->activityRows([
                    'auth:success',
                    'auth:fail',
                    'auth:checkpoint',
                    'auth:sftp.fail',
                ])],
                'forgot-password' => ['rows' => $this->activityRows([
                    'auth:password-reset-pin.requested',
                    'auth:password-reset-pin.completed',
                    'auth_failed_password_reset',
                ])],
                'change-password' => ['rows' => $this->activityRows([
                    'user:account.password-changed',
                    'event:password-reset',
                ])],
                'change-email' => ['rows' => $this->activityRows([
                    'user:account.email-changed',
                ])],
                'ticket' => $this->ticketRows(),
                default => ['rows' => []],
            },
        ]);
    }

    public function update(UpdateAdminLogSettingsRequest $request): RedirectResponse
    {
        $this->settings->save($request->normalize());

        try {
            $this->kernel->call('queue:restart');
        } catch (Throwable $exception) {
            report($exception);
            $this->alert->warning('Log channel settings were saved, but queue restart could not be triggered automatically.')->flash();

            return redirect()->route('admin.logs', ['tab' => $request->input('tab', 'new-account')]);
        }

        $this->alert->success('Log channel settings have been updated successfully.')->flash();

        return redirect()->route('admin.logs', ['tab' => $request->input('tab', 'new-account')]);
    }

    private function newAccountRows(): array
    {
        $users = User::query()
            ->with('oauthAccounts')
            ->latest('created_at')
            ->limit(50)
            ->get();

        $signupLogs = ActivityLog::query()
            ->with(['subjects.subject'])
            ->whereIn('event', ['auth:signup', 'auth:email-verified'])
            ->whereIn('id', function ($query) use ($users) {
                $query->selectRaw('MAX(activity_logs.id)')
                    ->from('activity_logs')
                    ->join('activity_log_subjects', 'activity_log_subjects.activity_log_id', '=', 'activity_logs.id')
                    ->where('activity_log_subjects.subject_type', User::class)
                    ->whereIn('activity_log_subjects.subject_id', $users->pluck('id')->all())
                    ->whereIn('activity_logs.event', ['auth:signup', 'auth:email-verified'])
                    ->groupBy('activity_log_subjects.subject_id');
            })
            ->get()
            ->keyBy(fn (ActivityLog $log) => optional($log->subjects->first())->subject_id);

        return $users
            ->map(fn (User $user) => [
                'created_at' => optional($user->created_at)?->format('Y-m-d H:i:s T') ?? 'Unknown',
                'username' => $user->username,
                'email' => $user->email,
                'name' => trim($user->name_first . ' ' . $user->name_last),
                'email_verified' => (bool) $user->is_email_verified,
                'oauth_providers' => $user->oauthAccounts->pluck('provider')->values()->all(),
                'last_seen_at' => optional($user->last_seen_at)?->format('Y-m-d H:i:s T') ?? 'Never',
                'signup_ip' => $this->signupLogIp($signupLogs->get($user->id)),
                'verification_status' => $this->signupVerificationStatus($signupLogs->get($user->id), $user),
            ])
            ->all();
    }

    private function paymentRows(): array
    {
        return [
            'invoices' => BillingInvoice::query()
                ->with(['user', 'order', 'subscription'])
                ->latest('created_at')
                ->limit(20)
                ->get()
                ->map(fn (BillingInvoice $invoice) => [
                    'created_at' => optional($invoice->created_at)?->format('Y-m-d H:i:s T') ?? 'Unknown',
                    'invoice_number' => $invoice->invoice_number,
                    'user' => $invoice->user?->email ?? 'Unknown user',
                    'type' => $invoice->type,
                    'status' => $invoice->status,
                    'provider' => $invoice->provider ?: 'n/a',
                    'amount' => sprintf('%s %.2f', strtoupper($invoice->currency), (float) $invoice->grand_total),
                    'order_status' => $invoice->order?->status ?? 'n/a',
                ])
                ->all(),
            'attempts' => BillingPaymentAttempt::query()
                ->with(['invoice.user', 'payment'])
                ->latest('created_at')
                ->limit(20)
                ->get()
                ->map(fn (BillingPaymentAttempt $attempt) => [
                    'created_at' => optional($attempt->created_at)?->format('Y-m-d H:i:s T') ?? 'Unknown',
                    'invoice_number' => $attempt->invoice?->invoice_number ?? 'n/a',
                    'user' => $attempt->invoice?->user?->email ?? 'Unknown user',
                    'provider' => $attempt->provider ?: 'n/a',
                    'status' => $attempt->status,
                    'checkout_reference' => $attempt->checkout_reference ?: 'n/a',
                    'failure_reason' => $attempt->failure_reason ?: 'None',
                ])
                ->all(),
            'orders' => BillingOrder::query()
                ->with(['user', 'server', 'approver'])
                ->latest('updated_at')
                ->limit(20)
                ->get()
                ->map(fn (BillingOrder $order) => [
                    'updated_at' => optional($order->updated_at)?->format('Y-m-d H:i:s T') ?? 'Unknown',
                    'user' => $order->user?->email ?? 'Unknown user',
                    'server_name' => $order->server_name,
                    'order_type' => $order->order_type,
                    'status' => $order->status,
                    'approver' => $order->approver?->email ?? 'n/a',
                    'failure' => $order->provision_failure_message ?: 'None',
                ])
                ->all(),
            'gateway_events' => BillingGatewayEvent::query()
                ->latest('created_at')
                ->limit(20)
                ->get()
                ->map(fn (BillingGatewayEvent $event) => [
                    'created_at' => optional($event->created_at)?->format('Y-m-d H:i:s T') ?? 'Unknown',
                    'provider' => $event->provider,
                    'event_type' => $event->event_type,
                    'status' => $event->status,
                    'transaction_id' => $event->provider_transaction_id ?: 'n/a',
                    'error' => $event->processing_error ?: 'None',
                ])
                ->all(),
        ];
    }

    private function ticketRows(): array
    {
        return [
            'tickets' => Ticket::query()
                ->with(['user', 'assignedAdmin'])
                ->latest('updated_at')
                ->limit(25)
                ->get()
                ->map(fn (Ticket $ticket) => [
                    'updated_at' => optional($ticket->updated_at)?->format('Y-m-d H:i:s T') ?? 'Unknown',
                    'ticket_number' => $ticket->ticket_number,
                    'user' => $ticket->user?->email ?? 'Unknown user',
                    'category' => $ticket->category,
                    'status' => $ticket->status,
                    'subject' => $ticket->subject,
                    'assigned_admin' => $ticket->assignedAdmin?->email ?? 'Unassigned',
                ])
                ->all(),
            'messages' => TicketMessage::query()
                ->with('ticket.user')
                ->latest('created_at')
                ->limit(25)
                ->get()
                ->map(fn (TicketMessage $message) => [
                    'created_at' => optional($message->created_at)?->format('Y-m-d H:i:s T') ?? 'Unknown',
                    'ticket_number' => $message->ticket?->ticket_number ?? 'n/a',
                    'ticket_user' => $message->ticket?->user?->email ?? 'Unknown user',
                    'author' => $message->author_display_name ?: 'Unknown',
                    'author_type' => $message->author_type,
                    'origin' => $message->origin,
                    'body' => Str::limit(trim($message->body), 140, '...'),
                ])
                ->all(),
            'activity' => $this->activityRows([
                'ticket:create',
                'ticket:message.user',
                'ticket:message.staff',
                'ticket:message.system',
                'ticket:reopen',
                'ticket:resolve',
                'ticket:close',
                'ticket:status.update',
            ], 40),
        ];
    }

    private function activityRows(array $events, int $limit = 50): array
    {
        return ActivityLog::query()
            ->with(['actor', 'subjects.subject'])
            ->whereIn('event', $events)
            ->latest('timestamp')
            ->limit($limit)
            ->get()
            ->map(fn (ActivityLog $log) => [
                'timestamp' => optional($log->timestamp)?->format('Y-m-d H:i:s T') ?? 'Unknown',
                'event' => $log->event,
                'actor' => $this->actorLabel($log),
                'subject' => $this->subjectLabel($log),
                'ip' => (string) ($log->properties['ip'] ?? $log->ip ?? 'Unknown'),
                'context' => $this->activityContext($log),
            ])
            ->all();
    }

    private function securityRows(int $limit = 50): array
    {
        $eventRows = SecurityEvent::query()
            ->with(['rule', 'incident', 'actor', 'target', 'node'])
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (SecurityEvent $event) => $this->securityFormatter->logRow($event));

        $activityRows = ActivityLog::query()
            ->with(['actor', 'subjects.subject'])
            ->where('event', 'security:break-glass-used')
            ->latest('timestamp')
            ->limit(15)
            ->get()
            ->map(fn (ActivityLog $log) => [
                'sort_at' => optional($log->timestamp)?->timestamp ?? 0,
                'timestamp' => optional($log->timestamp)?->format('Y-m-d H:i:s T') ?? 'Unknown',
                'event' => $log->event,
                'attack' => 'Break-Glass Access Used',
                'outcome' => 'Manual Bypass (Audit Only)',
                'source' => (string) ($log->properties['ip'] ?? $log->ip ?? 'Unknown'),
                'target' => $this->subjectLabel($log),
                'details' => $this->activityContext($log),
            ]);

        return $eventRows
            ->concat($activityRows)
            ->sortByDesc('sort_at')
            ->take($limit)
            ->map(fn (array $row) => Arr::except($row, ['sort_at']))
            ->values()
            ->all();
    }

    private function actorLabel(ActivityLog $log): string
    {
        $actor = $log->actor;
        if ($actor instanceof User) {
            return sprintf('%s (%s)', $actor->username, $actor->email);
        }

        return 'System / Anonymous';
    }

    private function subjectLabel(ActivityLog $log): string
    {
        $subject = optional($log->subjects->first())->subject;

        return match (true) {
            $subject instanceof User => sprintf('%s (%s)', $subject->username, $subject->email),
            $subject instanceof Ticket => $subject->ticket_number,
            default => $subject ? class_basename($subject) . ' #' . $subject->getKey() : 'n/a',
        };
    }

    private function activityContext(ActivityLog $log): string
    {
        $properties = $log->properties ? $log->properties->toArray() : [];

        unset($properties['ip'], $properties['useragent']);

        if ($properties === []) {
            return $log->description ?: 'No extra context.';
        }

        $pairs = [];
        foreach ($properties as $key => $value) {
            $pairs[] = sprintf('%s: %s', $key, $this->stringifyValue($value));
        }

        return Str::limit(implode(' | ', $pairs), 220, '...');
    }

    private function stringifyValue(mixed $value): string
    {
        if (is_scalar($value) || is_null($value)) {
            return (string) $value;
        }

        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: 'n/a';
    }

    private function signupLogIp(?ActivityLog $log): string
    {
        if (!$log) {
            return 'Unknown';
        }

        return (string) ($log->properties['ip'] ?? $log->ip ?? 'Unknown');
    }

    private function signupVerificationStatus(?ActivityLog $log, User $user): string
    {
        if ($user->is_email_verified) {
            return 'Verified';
        }

        if (!$log) {
            return 'Pending';
        }

        $stage = trim((string) ($log->properties['signup_stage'] ?? ''));

        return $stage !== '' ? $stage : 'Pending';
    }
}
