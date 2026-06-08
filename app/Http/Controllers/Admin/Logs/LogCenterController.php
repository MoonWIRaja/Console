<?php

namespace Pterodactyl\Http\Controllers\Admin\Logs;

use Throwable;
use Illuminate\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Http\RedirectResponse;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Pagination\LengthAwarePaginator;
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
    private const LOG_PER_PAGE = 25;
    private const SECTION_PER_PAGE = 10;

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

    private const ACCOUNT_VERIFICATION_FILTERS = [
        'verified' => 'Verified',
        'unverified' => 'Unverified',
    ];

    private const INVOICE_STATUSES = [
        BillingInvoice::STATUS_DRAFT,
        BillingInvoice::STATUS_OPEN,
        BillingInvoice::STATUS_PROCESSING,
        BillingInvoice::STATUS_PAID,
        BillingInvoice::STATUS_EXPIRED,
        BillingInvoice::STATUS_VOID,
        BillingInvoice::STATUS_FAILED,
        BillingInvoice::STATUS_REFUNDED,
        BillingInvoice::STATUS_PARTIALLY_REFUNDED,
    ];

    private const PAYMENT_ATTEMPT_STATUSES = [
        BillingPaymentAttempt::STATUS_INITIATED,
        BillingPaymentAttempt::STATUS_REDIRECTED,
        BillingPaymentAttempt::STATUS_CALLBACK_RECEIVED,
        BillingPaymentAttempt::STATUS_VERIFIED_PAID,
        BillingPaymentAttempt::STATUS_VERIFIED_FAILED,
        BillingPaymentAttempt::STATUS_CANCELLED,
        BillingPaymentAttempt::STATUS_REFUND_PENDING,
        BillingPaymentAttempt::STATUS_REFUNDED,
        BillingPaymentAttempt::STATUS_REFUND_FAILED,
    ];

    private const ORDER_STATUSES = [
        BillingOrder::STATUS_DRAFT,
        BillingOrder::STATUS_AWAITING_PAYMENT,
        BillingOrder::STATUS_PAID,
        BillingOrder::STATUS_QUEUED_PROVISION,
        BillingOrder::STATUS_PENDING,
        BillingOrder::STATUS_PROVISIONING,
        BillingOrder::STATUS_PROVISIONED,
        BillingOrder::STATUS_PROVISION_FAILED,
        BillingOrder::STATUS_CANCELLED,
        BillingOrder::STATUS_REFUNDED,
        BillingOrder::STATUS_REJECTED,
        BillingOrder::STATUS_FAILED,
    ];

    private const GATEWAY_EVENT_STATUSES = [
        BillingGatewayEvent::STATUS_RECEIVED,
        BillingGatewayEvent::STATUS_PROCESSED,
        BillingGatewayEvent::STATUS_FAILED,
    ];

    private const SECURITY_VERDICTS = [
        'observed_only',
        'challenged',
        'rate_limited',
        'blocked',
        'contained',
        'quarantined',
    ];

    private const TICKET_STATUSES = [
        Ticket::STATUS_WAITING_FOR_USER,
        Ticket::STATUS_WAITING_FOR_STAFF,
        Ticket::STATUS_RESOLVED,
        Ticket::STATUS_CLOSED,
    ];

    private const TICKET_MESSAGE_ORIGINS = [
        TicketMessage::ORIGIN_CONSOLE,
        TicketMessage::ORIGIN_DISCORD,
        TicketMessage::ORIGIN_AUTOMATION,
        TicketMessage::ORIGIN_CHECKOUT,
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
                'new-account' => $this->newAccountRows($request),
                'payment' => $this->paymentRows($request),
                'security' => $this->securityRows($request),
                'login' => $this->activityRows($request, [
                    'auth:success',
                    'auth:fail',
                    'auth:checkpoint',
                    'auth:sftp.fail',
                ], 'event', 'page'),
                'forgot-password' => $this->activityRows($request, [
                    'auth:password-reset-pin.requested',
                    'auth:password-reset-pin.completed',
                    'auth_failed_password_reset',
                ], 'event', 'page'),
                'change-password' => $this->activityRows($request, [
                    'user:account.password-changed',
                    'event:password-reset',
                ], 'event', 'page'),
                'change-email' => $this->activityRows($request, [
                    'user:account.email-changed',
                ], 'event', 'page'),
                'ticket' => $this->ticketRows($request),
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

    private function newAccountRows(Request $request): array
    {
        $verification = $this->selectedOption($request, 'verification', array_keys(self::ACCOUNT_VERIFICATION_FILTERS));
        $query = User::query()
            ->with('oauthAccounts')
            ->latest('created_at');

        if ($verification === 'verified') {
            $query->where('is_email_verified', true);
        } elseif ($verification === 'unverified') {
            $query->where('is_email_verified', false);
        }

        $users = $query->paginate(self::LOG_PER_PAGE, ['*'], 'page')->appends($request->except('page'));
        $userCollection = $users->getCollection();

        $signupLogs = ActivityLog::query()
            ->with(['subjects.subject'])
            ->whereIn('event', ['auth:signup', 'auth:email-verified'])
            ->whereIn('id', function ($query) use ($userCollection) {
                $query->selectRaw('MAX(activity_logs.id)')
                    ->from('activity_logs')
                    ->join('activity_log_subjects', 'activity_log_subjects.activity_log_id', '=', 'activity_logs.id')
                    ->where('activity_log_subjects.subject_type', User::class)
                    ->whereIn('activity_log_subjects.subject_id', $userCollection->pluck('id')->all())
                    ->whereIn('activity_logs.event', ['auth:signup', 'auth:email-verified'])
                    ->groupBy('activity_log_subjects.subject_id');
            })
            ->get()
            ->keyBy(fn (ActivityLog $log) => optional($log->subjects->first())->subject_id);

        $users->getCollection()->transform(fn (User $user) => [
                'created_at' => optional($user->created_at)?->format('Y-m-d H:i:s T') ?? 'Unknown',
                'username' => $user->username,
                'email' => $user->email,
                'name' => trim($user->name_first . ' ' . $user->name_last),
                'email_verified' => (bool) $user->is_email_verified,
                'oauth_providers' => $user->oauthAccounts->pluck('provider')->values()->all(),
                'last_seen_at' => optional($user->last_seen_at)?->format('Y-m-d H:i:s T') ?? 'Never',
                'signup_ip' => $this->signupLogIp($signupLogs->get($user->id)),
                'verification_status' => $this->signupVerificationStatus($signupLogs->get($user->id), $user),
            ]);

        return [
            'rows' => $users,
            'filter' => [
                'name' => 'verification',
                'value' => $verification,
                'options' => self::ACCOUNT_VERIFICATION_FILTERS,
                'pageName' => 'page',
                'placeholder' => 'All verification states',
                'label' => 'Verified',
            ],
        ];
    }

    private function paymentRows(Request $request): array
    {
        return [
            'invoices' => [
                'rows' => $this->paginateMapped(
                    $this->applyStatusFilter(
                        BillingInvoice::query()
                            ->with(['user', 'order', 'subscription'])
                            ->latest('created_at'),
                        $request,
                        'invoices_status',
                        self::INVOICE_STATUSES
                    ),
                    $request,
                    'invoices_page',
                    self::SECTION_PER_PAGE,
                    fn (BillingInvoice $invoice) => [
                        'created_at' => optional($invoice->created_at)?->format('Y-m-d H:i:s T') ?? 'Unknown',
                        'invoice_number' => $invoice->invoice_number,
                        'user' => $invoice->user?->email ?? 'Unknown user',
                        'type' => $invoice->type,
                        'status' => $invoice->status,
                        'provider' => $invoice->provider ?: 'n/a',
                        'amount' => sprintf('%s %.2f', strtoupper($invoice->currency), (float) $invoice->grand_total),
                        'order_status' => $invoice->order?->status ?? 'n/a',
                    ]
                ),
                'filter' => $this->filterMeta($request, 'invoices_status', self::INVOICE_STATUSES, 'invoices_page', 'All invoice statuses'),
            ],
            'attempts' => [
                'rows' => $this->paginateMapped(
                    $this->applyStatusFilter(
                        BillingPaymentAttempt::query()
                            ->with(['invoice.user', 'payment'])
                            ->latest('created_at'),
                        $request,
                        'attempts_status',
                        self::PAYMENT_ATTEMPT_STATUSES
                    ),
                    $request,
                    'attempts_page',
                    self::SECTION_PER_PAGE,
                    fn (BillingPaymentAttempt $attempt) => [
                        'created_at' => optional($attempt->created_at)?->format('Y-m-d H:i:s T') ?? 'Unknown',
                        'invoice_number' => $attempt->invoice?->invoice_number ?? 'n/a',
                        'user' => $attempt->invoice?->user?->email ?? 'Unknown user',
                        'provider' => $attempt->provider ?: 'n/a',
                        'status' => $attempt->status,
                        'checkout_reference' => $attempt->checkout_reference ?: 'n/a',
                        'failure_reason' => $attempt->failure_reason ?: 'None',
                    ]
                ),
                'filter' => $this->filterMeta($request, 'attempts_status', self::PAYMENT_ATTEMPT_STATUSES, 'attempts_page', 'All attempt statuses'),
            ],
            'orders' => [
                'rows' => $this->paginateMapped(
                    $this->applyStatusFilter(
                        BillingOrder::query()
                            ->with(['user', 'server', 'approver'])
                            ->latest('updated_at'),
                        $request,
                        'orders_status',
                        self::ORDER_STATUSES
                    ),
                    $request,
                    'orders_page',
                    self::SECTION_PER_PAGE,
                    fn (BillingOrder $order) => [
                        'updated_at' => optional($order->updated_at)?->format('Y-m-d H:i:s T') ?? 'Unknown',
                        'user' => $order->user?->email ?? 'Unknown user',
                        'server_name' => $order->server_name,
                        'order_type' => $order->order_type,
                        'status' => $order->status,
                        'approver' => $order->approver?->email ?? 'n/a',
                        'failure' => $order->provision_failure_message ?: 'None',
                    ]
                ),
                'filter' => $this->filterMeta($request, 'orders_status', self::ORDER_STATUSES, 'orders_page', 'All order statuses'),
            ],
            'gateway_events' => [
                'rows' => $this->paginateMapped(
                    $this->applyStatusFilter(
                        BillingGatewayEvent::query()->latest('created_at'),
                        $request,
                        'gateway_events_status',
                        self::GATEWAY_EVENT_STATUSES
                    ),
                    $request,
                    'gateway_events_page',
                    self::SECTION_PER_PAGE,
                    fn (BillingGatewayEvent $event) => [
                        'created_at' => optional($event->created_at)?->format('Y-m-d H:i:s T') ?? 'Unknown',
                        'provider' => $event->provider,
                        'event_type' => $event->event_type,
                        'status' => $event->status,
                        'transaction_id' => $event->provider_transaction_id ?: 'n/a',
                        'error' => $event->processing_error ?: 'None',
                    ]
                ),
                'filter' => $this->filterMeta($request, 'gateway_events_status', self::GATEWAY_EVENT_STATUSES, 'gateway_events_page', 'All gateway statuses'),
            ],
        ];
    }

    private function ticketRows(Request $request): array
    {
        return [
            'tickets' => [
                'rows' => $this->paginateMapped(
                    $this->applyStatusFilter(
                        Ticket::query()
                            ->with(['user', 'assignedAdmin'])
                            ->latest('updated_at'),
                        $request,
                        'tickets_status',
                        self::TICKET_STATUSES
                    ),
                    $request,
                    'tickets_page',
                    self::SECTION_PER_PAGE,
                    fn (Ticket $ticket) => [
                        'updated_at' => optional($ticket->updated_at)?->format('Y-m-d H:i:s T') ?? 'Unknown',
                        'ticket_number' => $ticket->ticket_number,
                        'user' => $ticket->user?->email ?? 'Unknown user',
                        'category' => $ticket->category,
                        'status' => $ticket->status,
                        'subject' => $ticket->subject,
                        'assigned_admin' => $ticket->assignedAdmin?->email ?? 'Unassigned',
                    ]
                ),
                'filter' => $this->filterMeta($request, 'tickets_status', self::TICKET_STATUSES, 'tickets_page', 'All ticket statuses'),
            ],
            'messages' => [
                'rows' => $this->paginateMapped(
                    $this->applyFieldFilter(
                        TicketMessage::query()
                            ->with('ticket.user')
                            ->latest('created_at'),
                        $request,
                        'messages_origin',
                        'origin',
                        self::TICKET_MESSAGE_ORIGINS
                    ),
                    $request,
                    'messages_page',
                    self::SECTION_PER_PAGE,
                    fn (TicketMessage $message) => [
                        'created_at' => optional($message->created_at)?->format('Y-m-d H:i:s T') ?? 'Unknown',
                        'ticket_number' => $message->ticket?->ticket_number ?? 'n/a',
                        'ticket_user' => $message->ticket?->user?->email ?? 'Unknown user',
                        'author' => $message->author_display_name ?: 'Unknown',
                        'author_type' => $message->author_type,
                        'origin' => $message->origin,
                        'body' => Str::limit(trim($message->body), 140, '...'),
                    ]
                ),
                'filter' => $this->filterMeta($request, 'messages_origin', self::TICKET_MESSAGE_ORIGINS, 'messages_page', 'All message origins', 'Origin'),
            ],
            'activity' => $this->activityRows($request, [
                'ticket:create',
                'ticket:message.user',
                'ticket:message.staff',
                'ticket:message.system',
                'ticket:reopen',
                'ticket:resolve',
                'ticket:close',
                'ticket:status.update',
            ], 'ticket_activity_event', 'ticket_activity_page', self::SECTION_PER_PAGE),
        ];
    }

    private function activityRows(Request $request, array $events, string $filterName, string $pageName, int $perPage = self::LOG_PER_PAGE): array
    {
        $event = $this->selectedOption($request, $filterName, $events);
        $query = ActivityLog::query()
            ->with(['actor', 'subjects.subject'])
            ->whereIn('event', $event ? [$event] : $events)
            ->latest('timestamp');

        return [
            'rows' => $this->paginateMapped(
                $query,
                $request,
                $pageName,
                $perPage,
                fn (ActivityLog $log) => [
                'timestamp' => optional($log->timestamp)?->format('Y-m-d H:i:s T') ?? 'Unknown',
                'event' => $log->event,
                'actor' => $this->actorLabel($log),
                'subject' => $this->subjectLabel($log),
                'ip' => (string) ($log->properties['ip'] ?? $log->ip ?? 'Unknown'),
                'context' => $this->activityContext($log),
                ]
            ),
            'filter' => [
                'name' => $filterName,
                'value' => $event,
                'options' => $this->statusOptions($events),
                'pageName' => $pageName,
                'placeholder' => 'All events',
                'label' => 'Event',
            ],
        ];
    }

    private function securityRows(Request $request): array
    {
        $verdict = $this->selectedOption($request, 'verdict', self::SECURITY_VERDICTS);
        $query = SecurityEvent::query()
            ->with(['rule', 'incident', 'actor', 'target', 'node'])
            ->latest();

        if ($verdict !== null) {
            $query->where('verdict', $verdict);
        }

        return [
            'rows' => $this->paginateMapped(
                $query,
                $request,
                'page',
                self::LOG_PER_PAGE,
                fn (SecurityEvent $event) => Arr::except($this->securityFormatter->logRow($event), ['sort_at'])
            ),
            'filter' => [
                'name' => 'verdict',
                'value' => $verdict,
                'options' => $this->statusOptions(self::SECURITY_VERDICTS),
                'pageName' => 'page',
                'placeholder' => 'All verdicts',
                'label' => 'Verdict',
            ],
        ];
    }

    private function applyStatusFilter($query, Request $request, string $name, array $statuses)
    {
        return $this->applyFieldFilter($query, $request, $name, 'status', $statuses);
    }

    private function applyFieldFilter($query, Request $request, string $name, string $field, array $allowed)
    {
        $value = $this->selectedOption($request, $name, $allowed);

        if ($value !== null) {
            $query->where($field, $value);
        }

        return $query;
    }

    private function paginateMapped($query, Request $request, string $pageName, int $perPage, callable $mapper): LengthAwarePaginator
    {
        $paginator = $query->paginate($perPage, ['*'], $pageName)->appends($request->except($pageName));
        $paginator->getCollection()->transform($mapper);

        return $paginator;
    }

    private function filterMeta(Request $request, string $name, array $statuses, string $pageName, string $placeholder, string $label = 'Status'): array
    {
        return [
            'name' => $name,
            'value' => $this->selectedOption($request, $name, $statuses),
            'options' => $this->statusOptions($statuses),
            'pageName' => $pageName,
            'placeholder' => $placeholder,
            'label' => $label,
        ];
    }

    private function selectedOption(Request $request, string $name, array $allowed): ?string
    {
        $value = (string) $request->query($name, '');

        if ($value === '') {
            return null;
        }

        return in_array($value, $allowed, true) ? $value : null;
    }

    private function statusOptions(array $statuses): array
    {
        return array_reduce($statuses, function (array $options, string $status): array {
            $options[$status] = ucwords(str_replace(['_', ':', '.', '-'], ' ', $status));

            return $options;
        }, []);
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
