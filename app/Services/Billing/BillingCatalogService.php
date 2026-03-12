<?php

namespace Pterodactyl\Services\Billing;

use Illuminate\Support\Collection;
use Pterodactyl\Models\Node;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Allocation;
use Pterodactyl\Models\EggVariable;
use Pterodactyl\Models\BillingOrder;
use Pterodactyl\Models\BillingSubscription;
use Pterodactyl\Models\BillingNodeConfig;
use Pterodactyl\Models\BillingGameProfile;

class BillingCatalogService
{
    public const DISK_STEP_GB = 10;

    /**
     * Return a normalized availability snapshot for a billing-enabled node.
     */
    public function getAvailability(BillingNodeConfig $config, ?BillingSubscription $excludingSubscription = null): array
    {
        $config->loadMissing('node', 'gameProfiles.egg.nest', 'gameProfiles.egg.variables');

        $reservedOrders = BillingOrder::query()
            ->where('billing_node_config_id', $config->id)
            ->whereIn('status', BillingOrder::ACTIVE_RESERVATION_STATUSES)
            ->where('order_type', BillingOrder::TYPE_NEW_SERVER)
            ->whereNull('server_id')
            ->whereNull('provisioned_at')
            ->selectRaw('COALESCE(SUM(memory_gb), 0) as memory_gb')
            ->selectRaw('COALESCE(SUM(disk_gb), 0) as disk_gb')
            ->first();

        $reservedSubscriptions = BillingSubscription::query()
            ->where('billing_node_config_id', $config->id)
            ->whereNotNull('server_id')
            ->whereIn('status', BillingSubscription::RESOURCE_RESERVATION_STATUSES)
            ->when($excludingSubscription, fn ($query) => $query->where('id', '!=', $excludingSubscription->id))
            ->selectRaw('COALESCE(SUM(memory_gb), 0) as memory_gb')
            ->selectRaw('COALESCE(SUM(disk_gb), 0) as disk_gb')
            ->first();

        $nodeResources = $this->getNodeResourceAvailability($config->node, $excludingSubscription?->server);
        $freeAllocations = Allocation::query()
            ->where('node_id', $config->node_id)
            ->whereNull('server_id')
            ->count();

        $reservedMemory = (int) $reservedOrders->memory_gb + (int) $reservedSubscriptions->memory_gb;
        $reservedDisk = (int) $reservedOrders->disk_gb + (int) $reservedSubscriptions->disk_gb;

        $billingMemoryRemaining = max($config->memory_stock_gb - $reservedMemory, 0);
        $billingDiskRemaining = max($config->disk_stock_gb - $reservedDisk, 0);
        $sellableMemoryRemaining = min($billingMemoryRemaining, $nodeResources['memory_remaining_gb']);
        $sellableDiskRemaining = $this->normalizeDiskStep(min($billingDiskRemaining, $nodeResources['disk_remaining_gb']));

        // Billing treats RAM and storage as a paired stock pool: if either one is exhausted,
        // the node should be considered sold out for new orders.
        if ($sellableMemoryRemaining < 1 || $sellableDiskRemaining < self::DISK_STEP_GB) {
            $sellableMemoryRemaining = 0;
            $sellableDiskRemaining = 0;
        }

        return [
            'free_allocations' => $freeAllocations,
            'cpu_remaining' => max($config->cpu_stock, 0),
            'memory_remaining_gb' => $sellableMemoryRemaining,
            'disk_remaining_gb' => $sellableDiskRemaining,
            'billing_cpu_remaining' => max($config->cpu_stock, 0),
            'billing_memory_remaining_gb' => $billingMemoryRemaining,
            'billing_disk_remaining_gb' => $billingDiskRemaining,
            'node_memory_remaining_gb' => $nodeResources['memory_remaining_gb'],
            'node_disk_remaining_gb' => $nodeResources['disk_remaining_gb'],
            'has_enabled_games' => $config->gameProfiles->where('enabled', true)->isNotEmpty(),
            'is_available' => $config->enabled
                && $freeAllocations > 0
                && $config->cpu_stock > 0
                && $sellableMemoryRemaining > 0
                && $sellableDiskRemaining >= self::DISK_STEP_GB
                && $config->gameProfiles->where('enabled', true)->isNotEmpty(),
        ];
    }

    /**
     * Build the client-side catalog for all enabled billing nodes.
     */
    public function getClientCatalog(): array
    {
        return BillingNodeConfig::query()
            ->with(['node', 'gameProfiles.egg.nest', 'gameProfiles.egg.variables'])
            ->where('enabled', true)
            ->orderBy('display_name')
            ->get()
            ->map(function (BillingNodeConfig $config) {
                $availability = $this->getAvailability($config);

                return [
                    'id' => $config->id,
                    'node_id' => $config->node_id,
                    'display_name' => $config->display_name,
                    'description' => $config->description,
                    'show_remaining_capacity' => $config->show_remaining_capacity,
                    'defaults' => [
                        'allocation_limit' => $config->default_allocation_limit,
                        'database_limit' => $config->default_database_limit,
                        'backup_limit' => $config->default_backup_limit,
                        'swap_mb' => $config->default_swap,
                        'io_weight' => $config->default_io,
                        'oom_disabled' => $config->default_oom_disabled,
                        'start_on_completion' => $config->start_on_completion,
                    ],
                    'pricing' => [
                        'per_vcore' => (float) $config->price_per_vcore,
                        'per_gb_ram' => (float) $config->price_per_gb_ram,
                        'per_10gb_disk' => (float) $config->price_per_10gb_disk,
                    ],
                    'availability' => $availability,
                    'limits' => [
                        'max_cpu' => $availability['cpu_remaining'],
                        'max_memory_gb' => $availability['memory_remaining_gb'],
                        'max_disk_gb' => $availability['disk_remaining_gb'],
                        'disk_step_gb' => self::DISK_STEP_GB,
                    ],
                    'games' => $config->gameProfiles
                        ->where('enabled', true)
                        ->sortBy(['position', 'display_name'])
                        ->values()
                        ->map(function (BillingGameProfile $profile) {
                            return [
                                'id' => $profile->id,
                                'egg_id' => $profile->egg_id,
                                'nest_id' => $profile->egg->nest_id,
                                'nest_name' => $profile->egg->nest->name,
                                'display_name' => $profile->display_name,
                                'description' => $profile->description,
                                'egg_name' => $profile->egg->name,
                                'variables' => $profile->egg->variables
                                    ->filter(fn (EggVariable $variable) => $this->shouldExposeVariableToClient($variable))
                                    ->values()
                                    ->map(function (EggVariable $variable) use ($profile) {
                                        $value = (string) (($profile->environment ?? [])[$variable->env_variable] ?? '');

                                        return [
                                            'name' => $variable->name,
                                            'description' => $variable->description,
                                            'env_variable' => $variable->env_variable,
                                            'default_value' => (string) ($variable->default_value ?? ''),
                                            'server_value' => $value !== '' ? $value : null,
                                            'is_editable' => $variable->user_editable,
                                            'rules' => $variable->rules,
                                        ];
                                    })
                                    ->all(),
                            ];
                        })
                        ->all(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Calculate order pricing for a set of requested resources.
     */
    public function calculatePricing(BillingNodeConfig $config, int $cpuCores, int $memoryGb, int $diskGb): array
    {
        $diskUnits = (int) ceil($diskGb / self::DISK_STEP_GB);

        $cpuTotal = round($cpuCores * (float) $config->price_per_vcore, 2);
        $memoryTotal = round($memoryGb * (float) $config->price_per_gb_ram, 2);
        $diskTotal = round($diskUnits * (float) $config->price_per_10gb_disk, 2);

        return [
            'cpu_total' => $cpuTotal,
            'memory_total' => $memoryTotal,
            'disk_total' => $diskTotal,
            'total' => round($cpuTotal + $memoryTotal + $diskTotal, 2),
        ];
    }

    public function getSubscriptionUpgradeLimits(BillingSubscription $subscription): array
    {
        $subscription->loadMissing('nodeConfig.node', 'server');

        $availability = $this->getAvailability($subscription->nodeConfig, $subscription);

        return [
            'max_cpu' => max($subscription->nodeConfig->cpu_stock, 0),
            'max_memory_gb' => max(min($availability['billing_memory_remaining_gb'], $availability['node_memory_remaining_gb']), 0),
            'max_disk_gb' => $this->normalizeDiskStep(max(min($availability['billing_disk_remaining_gb'], $availability['node_disk_remaining_gb']), 0)),
            'disk_step_gb' => self::DISK_STEP_GB,
            'free_allocations' => $availability['free_allocations'],
        ];
    }

    /**
     * Return the first available allocation on a node, or null if one does not exist.
     */
    public function getFirstFreeAllocation(int $nodeId): ?Allocation
    {
        return Allocation::query()
            ->where('node_id', $nodeId)
            ->whereNull('server_id')
            ->orderBy('id')
            ->first();
    }

    /**
     * Return all admin-facing node summaries including billing and capacity state.
     *
     * @param \Illuminate\Support\Collection<int, \Pterodactyl\Models\BillingNodeConfig> $configs
     * @return array<int, array<string, mixed>>
     */
    public function getAdminNodeSummaries(Collection $configs): array
    {
        return $configs->map(function (BillingNodeConfig $config) {
            return [
                'config' => $config,
                'availability' => $this->getAvailability($config),
            ];
        })->all();
    }

    /**
     * Return actual node-level remaining RAM and disk capacity in GB.
     */
    private function getNodeResourceAvailability(Node $node, ?Server $excludingServer = null): array
    {
        $usage = Server::query()
            ->where('node_id', $node->id)
            ->selectRaw('COALESCE(SUM(memory), 0) as used_memory')
            ->selectRaw('COALESCE(SUM(disk), 0) as used_disk')
            ->first();

        $usedMemory = (int) $usage->used_memory;
        $usedDisk = (int) $usage->used_disk;
        if ($excludingServer && $excludingServer->node_id === $node->id) {
            $usedMemory = max($usedMemory - (int) $excludingServer->memory, 0);
            $usedDisk = max($usedDisk - (int) $excludingServer->disk, 0);
        }

        $memoryLimitMb = (int) round($node->memory * (1 + ($node->memory_overallocate / 100)));
        $diskLimitMb = (int) round($node->disk * (1 + ($node->disk_overallocate / 100)));

        return [
            'memory_remaining_gb' => max((int) floor(max($memoryLimitMb - $usedMemory, 0) / 1024), 0),
            'disk_remaining_gb' => max((int) floor(max($diskLimitMb - $usedDisk, 0) / 1024), 0),
        ];
    }

    private function normalizeDiskStep(int $diskGb): int
    {
        if ($diskGb < self::DISK_STEP_GB) {
            return 0;
        }

        return (int) floor($diskGb / self::DISK_STEP_GB) * self::DISK_STEP_GB;
    }

    private function shouldExposeVariableToClient(EggVariable $variable): bool
    {
        return $variable->user_viewable;
    }
}
