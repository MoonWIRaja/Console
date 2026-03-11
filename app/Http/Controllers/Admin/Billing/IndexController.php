<?php

namespace Pterodactyl\Http\Controllers\Admin\Billing;

use Illuminate\View\View;
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
    public function __construct(
        private AlertsMessageBag $alert,
        private BillingCatalogService $catalogService,
        private BillingSettlementReconciliationService $reconciliationService,
    ) {
    }

    public function index(): View
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
                    'default_swap' => 0,
                    'default_io' => 500,
                    'default_oom_disabled' => true,
                    'start_on_completion' => true,
                ]
            );
        })->load(['node.location', 'gameProfiles']);

        return view('admin.billing.index', [
            'summaries' => $this->catalogService->getAdminNodeSummaries($configs),
            'recentOrders' => BillingOrder::query()->with(['user', 'server', 'node'])->latest()->limit(20)->get(),
            'recentInvoices' => BillingInvoice::query()->with('user')->latest()->limit(10)->get(),
            'recentPayments' => BillingPayment::query()->with('invoice.user')->latest()->limit(10)->get(),
            'activeSubscriptions' => BillingSubscription::query()->whereIn('status', [
                BillingSubscription::STATUS_ACTIVE,
                BillingSubscription::STATUS_PAST_DUE,
                BillingSubscription::STATUS_SUSPENDED,
            ])->count(),
            'pendingOrders' => BillingOrder::query()->where('status', BillingOrder::STATUS_PENDING)->count(),
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
}
