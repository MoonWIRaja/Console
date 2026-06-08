<?php

namespace Pterodactyl\Services\Billing;

use Illuminate\Support\Facades\DB;
use Pterodactyl\Models\Node;
use Pterodactyl\Models\BillingOrder;
use Pterodactyl\Models\BillingInvoice;
use Pterodactyl\Models\BillingNodeConfig;
use Pterodactyl\Models\BillingInvoiceItem;
use Pterodactyl\Models\BillingSubscription;

class BillingNodeSyncService
{
    private const OPEN_INVOICE_STATUSES = [
        BillingInvoice::STATUS_DRAFT,
        BillingInvoice::STATUS_OPEN,
        BillingInvoice::STATUS_PROCESSING,
    ];

    public function __construct(
        private BillingCatalogService $catalogService,
        private BillingDocumentService $documentService,
        private BillingTaxCalculationService $taxCalculationService,
    ) {
    }

    public function syncAll(): array
    {
        $summary = $this->emptySummary();

        Node::query()->orderBy('id')->chunkById(100, function ($nodes) use (&$summary) {
            foreach ($nodes as $node) {
                $summary = $this->mergeSummary($summary, $this->syncNode($node));
            }
        });

        return $summary;
    }

    public function syncNode(Node $node): array
    {
        $config = $this->getOrCreateConfig($node);
        $summary = $this->emptySummary();
        $summary['nodes_synced']++;

        if (!$config->exists) {
            return $summary;
        }

        if ((string) $config->display_name !== (string) $node->name) {
            $config->forceFill([
                'display_name' => $node->name,
            ])->saveQuietly();

            $summary['configs_renamed']++;
        }

        if ($config->wasRecentlyCreated) {
            $summary['configs_created']++;
        }

        return $this->mergeSummary($summary, $this->syncConfig($config->fresh(['node'])));
    }

    public function syncConfig(BillingNodeConfig $config, bool $syncNames = true, bool $syncPricing = true): array
    {
        $config->loadMissing('node');

        $summary = $this->emptySummary();
        $targetNodeName = $this->resolveNodeName($config);

        if ($syncNames) {
            $summary['subscriptions_renamed'] += BillingSubscription::query()
                ->where('billing_node_config_id', $config->id)
                ->where('node_name', '!=', $targetNodeName)
                ->update([
                    'node_name' => $targetNodeName,
                    'updated_at' => now(),
                ]);

            $summary['orders_renamed'] += BillingOrder::query()
                ->where('billing_node_config_id', $config->id)
                ->where('node_name', '!=', $targetNodeName)
                ->update([
                    'node_name' => $targetNodeName,
                    'updated_at' => now(),
                ]);
        }

        if (!$syncPricing) {
            return $summary;
        }

        BillingSubscription::query()
            ->where('billing_node_config_id', $config->id)
            ->whereIn('status', BillingSubscription::RESOURCE_RESERVATION_STATUSES)
            ->orderBy('id')
            ->chunkById(100, function ($subscriptions) use ($config, $targetNodeName, &$summary) {
                foreach ($subscriptions as $subscription) {
                    $pricing = $this->catalogService->calculatePricing(
                        $config,
                        (int) $subscription->cpu_cores,
                        (int) $subscription->memory_gb,
                        (int) $subscription->disk_gb
                    );

                    $subscription->forceFill([
                        'node_name' => $targetNodeName,
                        'price_per_vcore' => $config->price_per_vcore,
                        'price_per_gb_ram' => $config->price_per_gb_ram,
                        'price_per_10gb_disk' => $config->price_per_10gb_disk,
                        'recurring_total' => $pricing['total'],
                    ]);

                    if ($subscription->isDirty([
                        'node_name',
                        'price_per_vcore',
                        'price_per_gb_ram',
                        'price_per_10gb_disk',
                        'recurring_total',
                    ])) {
                        $subscription->saveOrFail();
                        $summary['subscriptions_repriced']++;
                    }

                    $summary = $this->mergeSummary(
                        $summary,
                        $this->syncOpenRenewalInvoicesForSubscription($subscription, $targetNodeName)
                    );
                }
            });

        return $summary;
    }

    private function syncOpenRenewalInvoicesForSubscription(BillingSubscription $subscription, string $targetNodeName): array
    {
        $summary = $this->emptySummary();

        BillingInvoice::query()
            ->with(['items', 'order'])
            ->where('subscription_id', $subscription->id)
            ->where('type', BillingInvoice::TYPE_RENEWAL)
            ->whereIn('status', self::OPEN_INVOICE_STATUSES)
            ->orderBy('id')
            ->get()
            ->each(function (BillingInvoice $invoice) use ($subscription, $targetNodeName, &$summary) {
                DB::transaction(function () use ($invoice, $subscription, $targetNodeName, &$summary) {
                    $invoiceChanged = false;
                    $subtotal = round((float) $subscription->recurring_total, 2);
                    $tax = $this->taxCalculationService->calculate(
                        BillingInvoice::TYPE_RENEWAL,
                        $invoice->billing_profile_snapshot ?? [],
                        $subtotal
                    );

                    if ($invoice->order) {
                        $invoice->order->forceFill([
                            'node_name' => $targetNodeName,
                            'price_per_vcore' => $subscription->price_per_vcore,
                            'price_per_gb_ram' => $subscription->price_per_gb_ram,
                            'price_per_10gb_disk' => $subscription->price_per_10gb_disk,
                            'cpu_total' => round($subscription->cpu_cores * (float) $subscription->price_per_vcore, 2),
                            'memory_total' => round($subscription->memory_gb * (float) $subscription->price_per_gb_ram, 2),
                            'disk_total' => round(
                                ceil($subscription->disk_gb / BillingCatalogService::DISK_STEP_GB) * (float) $subscription->price_per_10gb_disk,
                                2
                            ),
                            'total' => $subtotal,
                        ]);

                        if ($invoice->order->isDirty([
                            'node_name',
                            'price_per_vcore',
                            'price_per_gb_ram',
                            'price_per_10gb_disk',
                            'cpu_total',
                            'memory_total',
                            'disk_total',
                            'total',
                        ])) {
                            $invoice->order->saveOrFail();
                            $summary['renewal_orders_repriced']++;
                            $invoiceChanged = true;
                        }
                    }

                    $invoice->forceFill([
                        'subtotal' => $subtotal,
                        'tax_total' => round((float) ($tax['tax_total'] ?? 0), 2),
                        'grand_total' => round($subtotal + (float) ($tax['tax_total'] ?? 0), 2),
                    ]);

                    if ($invoice->isDirty(['subtotal', 'tax_total', 'grand_total'])) {
                        $invoice->saveOrFail();
                        $invoiceChanged = true;
                    }

                    if ($this->syncRenewalInvoiceItems($invoice, $subscription, $tax)) {
                        $invoiceChanged = true;
                    }

                    if ($invoiceChanged) {
                        $this->documentService->syncInvoiceUrl($invoice->fresh(['items', 'order', 'subscription']));
                        $summary['renewal_invoices_repriced']++;
                    }
                });
            });

        return $summary;
    }

    private function syncRenewalInvoiceItems(BillingInvoice $invoice, BillingSubscription $subscription, array $tax): bool
    {
        $changed = false;
        $periodMonths = max((int) $subscription->renewal_period_months, 1);
        $subtotal = round((float) $subscription->recurring_total, 2);

        $baseItem = $invoice->items->firstWhere('type', BillingInvoiceItem::TYPE_BASE_PLAN);
        $basePayload = [
            'description' => sprintf('Renewal for %s', $subscription->server_name),
            'quantity' => $periodMonths,
            'unit_amount' => round($subtotal / $periodMonths, 2),
            'line_subtotal' => $subtotal,
            'meta' => [
                'subscription_id' => $subscription->id,
                'period_months' => $subscription->renewal_period_months,
            ],
        ];

        if ($baseItem) {
            $baseItem->forceFill($basePayload);
            if ($baseItem->isDirty(['description', 'quantity', 'unit_amount', 'line_subtotal', 'meta'])) {
                $baseItem->saveOrFail();
                $changed = true;
            }
        } else {
            $invoice->items()->create(array_merge($basePayload, [
                'type' => BillingInvoiceItem::TYPE_BASE_PLAN,
            ]));
            $changed = true;
        }

        $existingTaxItems = $invoice->items
            ->where('type', BillingInvoiceItem::TYPE_TAX)
            ->map(fn (BillingInvoiceItem $item) => [
                'description' => (string) $item->description,
                'quantity' => (int) $item->quantity,
                'unit_amount' => round((float) $item->unit_amount, 2),
                'line_subtotal' => round((float) $item->line_subtotal, 2),
                'meta' => $item->meta ?? [],
            ])
            ->values()
            ->all();

        $desiredTaxItems = collect($tax['items'] ?? [])
            ->map(fn (array $taxItem) => [
                'description' => (string) $taxItem['name'],
                'quantity' => 1,
                'unit_amount' => round((float) $taxItem['amount'], 2),
                'line_subtotal' => round((float) $taxItem['amount'], 2),
                'meta' => [
                    'rule_id' => $taxItem['rule_id'] ?? null,
                    'rate_type' => $taxItem['rate_type'] ?? null,
                    'rate_value' => $taxItem['rate_value'] ?? null,
                ],
            ])
            ->values()
            ->all();

        if ($existingTaxItems === $desiredTaxItems) {
            return $changed;
        }

        $invoice->items()->where('type', BillingInvoiceItem::TYPE_TAX)->delete();
        $changed = true;

        foreach ($desiredTaxItems as $taxItem) {
            $invoice->items()->create(array_merge($taxItem, [
                'type' => BillingInvoiceItem::TYPE_TAX,
            ]));
        }

        return $changed;
    }

    private function getOrCreateConfig(Node $node): BillingNodeConfig
    {
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
    }

    private function resolveNodeName(BillingNodeConfig $config): string
    {
        $displayName = trim((string) $config->display_name);
        if ($displayName !== '') {
            return $displayName;
        }

        return trim((string) ($config->node?->name ?? 'Unknown Node'));
    }

    private function emptySummary(): array
    {
        return [
            'nodes_synced' => 0,
            'configs_created' => 0,
            'configs_renamed' => 0,
            'subscriptions_renamed' => 0,
            'subscriptions_repriced' => 0,
            'orders_renamed' => 0,
            'renewal_orders_repriced' => 0,
            'renewal_invoices_repriced' => 0,
        ];
    }

    private function mergeSummary(array $base, array $extra): array
    {
        foreach ($extra as $key => $value) {
            $base[$key] = ($base[$key] ?? 0) + $value;
        }

        return $base;
    }
}
