<?php

namespace Pterodactyl\Services\Billing;

use Throwable;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Pterodactyl\Models\Egg;
use Pterodactyl\Models\User;
use Pterodactyl\Models\BillingOrder;
use Pterodactyl\Notifications\BillingProvisioningFailed;
use Pterodactyl\Services\Servers\ServerCreationService;

class BillingOrderProvisionService
{
    public function __construct(
        private BillingCatalogService $catalogService,
        private ServerCreationService $serverCreationService,
        private BillingSubscriptionService $subscriptionService,
    ) {
    }

    /**
     * Attempt to provision a billing order into a real panel server.
     *
     * @throws \Throwable
     */
    public function handle(BillingOrder $order, ?User $admin = null): BillingOrder
    {
        $order->loadMissing('nodeConfig', 'user', 'invoice');

        if ($order->order_type !== BillingOrder::TYPE_NEW_SERVER) {
            throw new \RuntimeException('Only new server billing orders can be provisioned.');
        }

        if (!in_array($order->status, [
            BillingOrder::STATUS_PENDING,
            BillingOrder::STATUS_PAID,
            BillingOrder::STATUS_QUEUED_PROVISION,
            BillingOrder::STATUS_PROVISION_FAILED,
        ], true)) {
            throw new \RuntimeException('Only paid or manually approved billing orders can be provisioned.');
        }

        $availability = $this->catalogService->getAvailability($order->nodeConfig);
        if ($availability['memory_remaining_gb'] < $order->memory_gb) {
            throw new \RuntimeException('There is no longer enough RAM available for this order.');
        }

        if ($availability['disk_remaining_gb'] < $order->disk_gb) {
            throw new \RuntimeException('There is no longer enough storage available for this order.');
        }

        $allocation = $this->catalogService->getFirstFreeAllocation($order->node_id);
        if (!$allocation) {
            throw new \RuntimeException('There are no free allocations left on this node.');
        }

        $order->forceFill([
            'status' => BillingOrder::STATUS_PROVISIONING,
            'approved_by' => $admin?->id,
            'approved_at' => $order->approved_at ?? CarbonImmutable::now(),
            'provision_attempted_at' => CarbonImmutable::now(),
            'provision_failure_code' => null,
            'provision_failure_message' => null,
        ])->saveOrFail();

        try {
            $egg = Egg::query()->findOrFail($order->egg_id);
            $server = $this->serverCreationService->handle([
                'name' => $order->server_name,
                'description' => sprintf('Provisioned from billing order #%d.', $order->id),
                'owner_id' => $order->user_id,
                'node_id' => $order->node_id,
                'allocation_id' => $allocation->id,
                'nest_id' => $egg->nest_id,
                'egg_id' => $egg->id,
                // Pterodactyl stores CPU as a percentage, so 100 = 1 vCore.
                'cpu' => $order->cpu_cores * 100,
                'memory' => $order->memory_gb * 1024,
                'disk' => $order->disk_gb * 1024,
                'swap' => $order->swap,
                'io' => $order->io,
                'oom_disabled' => $order->oom_disabled,
                'allocation_limit' => $order->allocation_limit,
                'database_limit' => $order->database_limit,
                'backup_limit' => $order->backup_limit,
                'startup' => $order->startup ?: $egg->startup,
                'image' => $this->resolveDockerImage($egg, $order->docker_image),
                'environment' => Arr::wrap($order->environment ?? []),
                'start_on_completion' => $order->start_on_completion,
            ]);
        } catch (Throwable $exception) {
            $this->markFailed($order, $exception->getMessage());

            throw $exception;
        }

        $order->forceFill([
            'status' => BillingOrder::STATUS_PROVISIONED,
            'server_id' => $server->id,
            'provisioned_at' => CarbonImmutable::now(),
        ])->saveOrFail();

        $this->subscriptionService->createFromProvisionedOrder($order);

        return $order->fresh(['server', 'user']);
    }

    /**
     * Mark an order as rejected by an administrator.
     */
    public function reject(BillingOrder $order, User $admin, ?string $notes = null): BillingOrder
    {
        if ($order->status !== BillingOrder::STATUS_PENDING) {
            throw new \RuntimeException('Only pending billing orders can be rejected.');
        }

        $order->forceFill([
            'status' => BillingOrder::STATUS_REJECTED,
            'approved_by' => $admin->id,
            'admin_notes' => $notes,
            'rejected_at' => CarbonImmutable::now(),
        ])->saveOrFail();

        return $order;
    }

    /**
     * Persist a failed provisioning outcome back onto the order.
     */
    private function markFailed(BillingOrder $order, string $message): void
    {
        $order->forceFill([
            'status' => BillingOrder::STATUS_PROVISION_FAILED,
            'failed_at' => CarbonImmutable::now(),
            'provision_failure_code' => 'provision_failed',
            'provision_failure_message' => $message,
            'admin_notes' => trim(implode("\n\n", array_filter([
                $order->admin_notes,
                $message,
            ]))),
        ])->saveOrFail();

        $order->user?->notify(new BillingProvisioningFailed($order->fresh('user')));
    }

    private function resolveDockerImage(Egg $egg, ?string $override): string
    {
        if (!empty($override)) {
            return $override;
        }

        return (string) collect($egg->docker_images)->first();
    }
}
