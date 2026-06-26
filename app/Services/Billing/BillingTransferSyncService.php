<?php

namespace Pterodactyl\Services\Billing;

use Pterodactyl\Models\Node;
use Pterodactyl\Models\Server;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Models\BillingNodeConfig;
use Pterodactyl\Models\BillingSubscription;
use Pterodactyl\Models\BillingSubscriptionRevision;

class BillingTransferSyncService
{
    public function __construct(
        private BillingNodeSyncService $nodeSync,
        private BillingSubscriptionRevisionService $revisionService,
    ) {
    }

    /**
     * Keep billing in sync after a server has finished transferring to a new node.
     *
     * Pricing is per-node, so the server's subscription must be re-pointed at the
     * destination node's billing config and repriced at that node's rates (along with
     * any open renewal invoice). If the destination node has no usable billing config
     * we deliberately leave the existing pricing untouched rather than zero it out,
     * and surface a warning so an administrator can configure the node and re-sync.
     */
    public function handleCompletedTransfer(Server $server, Node $node): void
    {
        /** @var BillingSubscription|null $subscription */
        $subscription = $server->billingSubscription()->first();
        if (is_null($subscription)) {
            return;
        }

        if (!in_array($subscription->status, BillingSubscription::RESOURCE_RESERVATION_STATUSES, true)) {
            return;
        }

        $config = BillingNodeConfig::query()
            ->where('node_id', $node->id)
            ->where('enabled', true)
            ->first();

        if (is_null($config)) {
            Log::warning('Server transferred to a node without an enabled billing config; subscription pricing left unchanged.', [
                'server_id' => $server->id,
                'subscription_id' => $subscription->id,
                'new_node_id' => $node->id,
            ]);

            return;
        }

        $previousNodeConfigId = $subscription->billing_node_config_id;
        $previousTotal = (string) $subscription->recurring_total;

        DB::transaction(function () use ($subscription, $config) {
            $this->nodeSync->repriceSubscription($subscription, $config);
            $this->revisionService->record($subscription->refresh(), BillingSubscriptionRevision::TYPE_NODE_TRANSFER);
        });

        Log::info('Subscription billing re-synced after server transfer.', [
            'server_id' => $server->id,
            'subscription_id' => $subscription->id,
            'previous_node_config_id' => $previousNodeConfigId,
            'new_node_config_id' => $config->id,
            'previous_recurring_total' => $previousTotal,
            'new_recurring_total' => (string) $subscription->refresh()->recurring_total,
        ]);
    }
}
