<?php

namespace Pterodactyl\Http\Controllers\Admin\Billing;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Pterodactyl\Models\Node;
use Pterodactyl\Models\BillingOrder;
use Pterodactyl\Models\BillingInvoice;
use Pterodactyl\Models\BillingPayment;
use Pterodactyl\Models\BillingSubscription;
use Pterodactyl\Models\BillingNodeConfig;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Billing\BillingCatalogService;
use Pterodactyl\Services\Billing\BillingSettlementReconciliationService;

class IndexController extends Controller
{
    private const TABLE_PER_PAGE = 10;

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

    private const PAYMENT_STATUSES = [
        BillingPayment::STATUS_INITIATED,
        BillingPayment::STATUS_REDIRECTED,
        BillingPayment::STATUS_CALLBACK_RECEIVED,
        BillingPayment::STATUS_VERIFIED_PAID,
        BillingPayment::STATUS_VERIFIED_FAILED,
        BillingPayment::STATUS_CANCELLED,
        BillingPayment::STATUS_REFUND_PENDING,
        BillingPayment::STATUS_REFUNDED,
        BillingPayment::STATUS_REFUND_FAILED,
    ];

    public function __construct(
        private AlertsMessageBag $alert,
        private BillingCatalogService $catalogService,
        private BillingSettlementReconciliationService $reconciliationService,
    ) {
    }

    public function index(Request $request): View
    {
        $nodes = Node::query()->with('location')->orderBy('name')->get();
        $configs = $nodes->map(function (Node $node) {
            return BillingNodeConfig::query()->firstOrCreate(
                ['node_id' => $node->id],
                [
                    'display_name' => $node->name,
                    'description' => $node->description,
                    'cpu_stock' => 0,
                    'memory_stock_gb' => max((int) floor($node->memory / 1024), 0),
                    'disk_stock_gb' => max((int) floor($node->disk / 1024), 0),
                    'show_remaining_capacity' => true,
                    'price_per_vcore' => 0,
                    'price_per_gb_ram' => 0,
                    'price_per_10gb_disk' => 0,
                    'default_allocation_limit' => 0,
                    'default_database_limit' => 0,
                    'default_backup_limit' => 0,
                    'default_split_limit' => 0,
                    'default_swap' => 0,
                    'default_io' => 500,
                    'default_oom_disabled' => true,
                    'start_on_completion' => true,
                ]
            );
        })->load(['node.location', 'gameProfiles']);

        return view('admin.billing.index', [
            'summaries' => $this->catalogService->getAdminNodeSummaries($configs),
            'recentOrders' => $this->paginateStatusTable(
                BillingOrder::query()->with(['user', 'server', 'node'])->latest(),
                $request,
                'orders_status',
                self::ORDER_STATUSES,
                'orders_page'
            ),
            'recentInvoices' => $this->paginateStatusTable(
                BillingInvoice::query()->with('user')->latest(),
                $request,
                'invoices_status',
                self::INVOICE_STATUSES,
                'invoices_page'
            ),
            'recentPayments' => $this->paginateStatusTable(
                BillingPayment::query()->with('invoice.user')->latest(),
                $request,
                'payments_status',
                self::PAYMENT_STATUSES,
                'payments_page'
            ),
            'orderStatusOptions' => $this->statusOptions(self::ORDER_STATUSES),
            'invoiceStatusOptions' => $this->statusOptions(self::INVOICE_STATUSES),
            'paymentStatusOptions' => $this->statusOptions(self::PAYMENT_STATUSES),
            'selectedOrderStatus' => $this->selectedStatus($request, 'orders_status', self::ORDER_STATUSES),
            'selectedInvoiceStatus' => $this->selectedStatus($request, 'invoices_status', self::INVOICE_STATUSES),
            'selectedPaymentStatus' => $this->selectedStatus($request, 'payments_status', self::PAYMENT_STATUSES),
            'activeSubscriptions' => BillingSubscription::query()->whereIn('status', [
                BillingSubscription::STATUS_ACTIVE,
                BillingSubscription::STATUS_PAST_DUE,
                BillingSubscription::STATUS_SUSPENDED,
            ])->count(),
            'pendingOrders' => BillingOrder::query()->where('status', BillingOrder::STATUS_AWAITING_PAYMENT)->count(),
            'openInvoices' => BillingInvoice::query()->whereIn('status', [
                BillingInvoice::STATUS_OPEN,
                BillingInvoice::STATUS_PROCESSING,
            ])->count(),
            'reconciliation' => $this->reconciliationService->summarize(),
        ]);
    }

    public function redirect(): RedirectResponse
    {
        $this->alert->warning('The requested billing page could not be found.')->flash();

        return redirect()->route('admin.billing');
    }

    private function paginateStatusTable(
        Builder $query,
        Request $request,
        string $statusKey,
        array $allowedStatuses,
        string $pageName
    ): LengthAwarePaginator {
        $status = $this->selectedStatus($request, $statusKey, $allowedStatuses);

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query
            ->paginate(self::TABLE_PER_PAGE, ['*'], $pageName)
            ->appends($request->except($pageName));
    }

    private function selectedStatus(Request $request, string $key, array $allowedStatuses): ?string
    {
        $status = (string) $request->query($key, '');

        return in_array($status, $allowedStatuses, true) ? $status : null;
    }

    private function statusOptions(array $statuses): array
    {
        return array_reduce($statuses, function (array $options, string $status): array {
            $options[$status] = ucwords(str_replace('_', ' ', $status));

            return $options;
        }, []);
    }
}
