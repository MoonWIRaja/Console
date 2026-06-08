<?php

namespace Pterodactyl\Console\Commands\Billing;

use Illuminate\Console\Command;
use Pterodactyl\Models\Node;
use Pterodactyl\Services\Billing\BillingNodeSyncService;

class SyncBillingNodeDataCommand extends Command
{
    protected $signature = 'billing:sync-node-data {--node=* : Limit sync to one or more node IDs}';

    protected $description = 'Sync billing node names and active subscription pricing with the latest node billing configuration.';

    public function __construct(private BillingNodeSyncService $syncService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $nodeIds = collect($this->option('node'))
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values();

        $summary = [
            'nodes_synced' => 0,
            'configs_created' => 0,
            'configs_renamed' => 0,
            'subscriptions_renamed' => 0,
            'subscriptions_repriced' => 0,
            'orders_renamed' => 0,
            'renewal_orders_repriced' => 0,
            'renewal_invoices_repriced' => 0,
        ];

        if ($nodeIds->isEmpty()) {
            $summary = $this->syncService->syncAll();
        } else {
            $nodes = Node::query()->whereIn('id', $nodeIds)->orderBy('id')->get();
            $foundIds = $nodes->pluck('id')->all();
            $missingIds = $nodeIds->reject(fn (int $id) => in_array($id, $foundIds, true))->values()->all();

            foreach ($nodes as $node) {
                $summary = $this->mergeSummary($summary, $this->syncService->syncNode($node));
            }

            if (!empty($missingIds)) {
                $this->warn('Skipped unknown node IDs: ' . implode(', ', $missingIds));
            }
        }

        foreach ($summary as $label => $count) {
            $this->line(sprintf('%s: %d', $label, $count));
        }

        return self::SUCCESS;
    }

    private function mergeSummary(array $base, array $extra): array
    {
        foreach ($extra as $key => $value) {
            $base[$key] = ($base[$key] ?? 0) + $value;
        }

        return $base;
    }
}
