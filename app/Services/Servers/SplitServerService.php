<?php

namespace Pterodactyl\Services\Servers;

use Throwable;
use Illuminate\Support\Arr;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Allocation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Models\EggVariable;
use Pterodactyl\Models\Subuser;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Services\Subusers\SubuserCreationService;

class SplitServerService
{
    public function __construct(
        private BuildModificationService $buildModificationService,
        private ServerCreationService $serverCreationService,
        private ServerDeletionService $serverDeletionService,
        private SubuserCreationService $subuserCreationService,
    ) {
    }

    public function overview(Server $server): array
    {
        $server->loadMissing(['allocation', 'splitParentServer']);

        $root = $this->resolveRootServer($server);
        $family = $this->familyFor($root);
        $current = $family->firstWhere('id', $server->id) ?? $server;
        $available = $this->availableResources($current);
        $remainingSplits = max(0, (int) $root->split_limit - max(0, $family->count() - 1));
        $canCreate = $this->canCreateFrom($current, $root, $family);

        return [
            'splitLimit' => (int) $root->split_limit,
            'splitsUsed' => max(0, $family->count() - 1),
            'remainingSplits' => $remainingSplits,
            'canCreate' => $canCreate['allowed'],
            'createBlockedReason' => $canCreate['reason'],
            'available' => $available,
            'familyTotals' => [
                'memory' => (int) $family->sum('memory'),
                'disk' => (int) $family->sum('disk'),
                'cpu' => (int) $family->sum('cpu'),
                'swap' => (int) $family->sum('swap'),
            ],
            'servers' => $family->map(fn (Server $member) => $this->serializeFamilyMember($member, $server, $root))->values()->all(),
        ];
    }

    public function create(Server $server, array $data, bool $copySubusers = false): Server
    {
        $server->loadMissing(['allocation', 'egg', 'nest', 'variables', 'subusers']);

        $root = $this->resolveRootServer($server);
        $family = $this->familyFor($root);
        $gate = $this->canCreateFrom($server, $root, $family);
        if (!$gate['allowed']) {
            throw new DisplayException($gate['reason'] ?? 'This server cannot be split right now.');
        }

        $allocation = Allocation::query()
            ->where('node_id', $server->node_id)
            ->whereNull('server_id')
            ->orderBy('id')
            ->first();

        if (!$allocation) {
            throw new DisplayException('There are no free allocations left on this node for a split server.');
        }

        $cpu = (int) Arr::get($data, 'cpu');
        $memory = (int) Arr::get($data, 'memory');
        $disk = (int) Arr::get($data, 'disk');
        $swap = (int) Arr::get($data, 'swap');

        $available = $this->availableResources($server);
        if ($cpu > $available['cpu']) {
            throw new DisplayException('You need more CPU available on this server before splitting it.');
        }
        if ($memory > $available['memory']) {
            throw new DisplayException('You need more memory available on this server before splitting it.');
        }
        if ($disk > $available['disk']) {
            throw new DisplayException('You need more disk available on this server before splitting it.');
        }
        if ($swap > $available['swap']) {
            throw new DisplayException('You need more swap available on this server before splitting it.');
        }

        $child = $this->serverCreationService->handle([
            'name' => trim((string) Arr::get($data, 'name')),
            'description' => sprintf('Split server created from %s.', $server->name),
            'owner_id' => $server->owner_id,
            'node_id' => $server->node_id,
            'allocation_id' => $allocation->id,
            'allocation_additional' => [],
            'database_limit' => $server->database_limit,
            'allocation_limit' => $server->allocation_limit,
            'backup_limit' => $server->backup_limit,
            'split_limit' => $root->split_limit,
            'memory' => $memory,
            'disk' => $disk,
            'swap' => $swap,
            'io' => $server->io,
            'cpu' => $cpu,
            'threads' => $server->threads,
            'nest_id' => $server->nest_id,
            'egg_id' => $server->egg_id,
            'startup' => $server->startup,
            'image' => $server->image,
            'environment' => $this->environmentFor($server),
            'oom_disabled' => $server->oom_disabled,
            'start_on_completion' => true,
            'split_parent_server_id' => $server->id,
            'split_root_server_id' => $root->id,
        ]);

        try {
            $this->buildModificationService->handle($server, $this->buildPayload($server, [
                'memory' => $server->memory - $memory,
                'disk' => $server->disk - $disk,
                'swap' => $server->swap - $swap,
                'cpu' => $server->cpu - $cpu,
            ]));
        } catch (Throwable $exception) {
            $this->serverDeletionService->withForce()->handle($child);

            throw $exception;
        }

        if ($copySubusers) {
            $this->copySubusers($server, $child);
        }

        return $child->refresh();
    }

    public function delete(Server $current, Server $target, ?string $confirmName = null): void
    {
        $root = $this->resolveRootServer($current);
        $family = $this->familyFor($root);
        $split = $family->firstWhere('id', $target->id);

        if (!$split) {
            throw new DisplayException('That split server does not belong to this server family.');
        }

        if ($split->id === $root->id) {
            throw new DisplayException('The root server for a split family cannot be removed from the split page.');
        }

        if ($split->id === $current->id) {
            throw new DisplayException('Delete the current server from the main server management page instead.');
        }

        if ($confirmName !== null && trim($confirmName) !== $split->name) {
            throw new DisplayException('The confirmation name does not match the server you are trying to delete.');
        }

        if ($split->splitChildServers()->exists()) {
            throw new DisplayException('Delete this server\'s split children first before removing it.');
        }

        $parent = $split->splitParentServer()->first();
        if (!$parent) {
            throw new DisplayException('This split server no longer has a valid parent server to refund resources to.');
        }

        $refund = [
            'memory' => $parent->memory + $split->memory,
            'disk' => $parent->disk + $split->disk,
            'swap' => $parent->swap + $split->swap,
            'cpu' => $parent->cpu + $split->cpu,
        ];

        try {
            $this->serverDeletionService->handle($split);
            $this->refundParent($parent, $refund);
        } catch (Throwable $exception) {
            if ($this->recoverFailedDelete($split, $parent, $refund, $exception)) {
                return;
            }

            throw $exception;
        }
    }

    private function buildPayload(Server $server, array $overrides = []): array
    {
        return array_merge([
            'allocation_id' => $server->allocation_id,
            'memory' => $server->memory,
            'swap' => $server->swap,
            'io' => $server->io,
            'cpu' => $server->cpu,
            'threads' => $server->threads,
            'disk' => $server->disk,
            'database_limit' => $server->database_limit,
            'allocation_limit' => $server->allocation_limit,
            'backup_limit' => $server->backup_limit,
            'split_limit' => $server->split_limit,
            'oom_disabled' => $server->oom_disabled,
        ], $overrides);
    }

    private function refundParent(Server $parent, array $refund): void
    {
        $parent = $parent->refresh();

        if (
            (int) $parent->memory === (int) $refund['memory']
            && (int) $parent->disk === (int) $refund['disk']
            && (int) $parent->swap === (int) $refund['swap']
            && (int) $parent->cpu === (int) $refund['cpu']
        ) {
            return;
        }

        $this->buildModificationService->handle($parent, $this->buildPayload($parent, $refund));
    }

    private function recoverFailedDelete(Server $split, Server $parent, array $refund, Throwable $exception): bool
    {
        usleep(500000);

        try {
            $freshSplit = Server::query()->find($split->id);
            if ($freshSplit) {
                $this->serverDeletionService->handle($freshSplit);
            }

            $freshParent = Server::query()->find($parent->id);
            if (!$freshParent) {
                return false;
            }

            $this->refundParent($freshParent, $refund);

            return !Server::query()->whereKey($split->id)->exists();
        } catch (Throwable $recoveryException) {
            Log::warning('Failed to recover a partially deleted split server.', [
                'split_server_id' => $split->id,
                'parent_server_id' => $parent->id,
                'original_error' => $exception->getMessage(),
                'recovery_error' => $recoveryException->getMessage(),
            ]);

            return false;
        }
    }

    private function availableResources(Server $server): array
    {
        return [
            'cpu' => max(0, $server->cpu - 1),
            'memory' => max(0, $server->memory - 512),
            'disk' => max(0, $server->disk - 512),
            'swap' => max(0, $server->swap),
        ];
    }

    private function canCreateFrom(Server $server, Server $root, Collection $family): array
    {
        if ($root->split_limit < 1) {
            return ['allowed' => false, 'reason' => 'This server family has no split slots configured.'];
        }

        if ($family->count() - 1 >= $root->split_limit) {
            return ['allowed' => false, 'reason' => 'This server family has already reached its split limit.'];
        }

        if ($server->cpu <= 1 || $server->memory <= 512 || $server->disk <= 512) {
            return ['allowed' => false, 'reason' => 'This server no longer has enough remaining resources to split.'];
        }

        if ($server->cpu === 0 || $server->memory === 0 || $server->disk === 0 || $server->swap < 0) {
            return ['allowed' => false, 'reason' => 'Unlimited resource values are not supported for split server creation.'];
        }

        $hasAllocation = Allocation::query()
            ->where('node_id', $server->node_id)
            ->whereNull('server_id')
            ->exists();

        if (!$hasAllocation) {
            return ['allowed' => false, 'reason' => 'No free allocations are available on this node.'];
        }

        return ['allowed' => true, 'reason' => null];
    }

    private function copySubusers(Server $source, Server $child): void
    {
        $source->loadMissing('subusers.user');

        /** @var Subuser $subuser */
        foreach ($source->subusers as $subuser) {
            try {
                $this->subuserCreationService->handle($child, $subuser->user->email, $subuser->permissions);
            } catch (Throwable $exception) {
                Log::warning('Failed to copy a split subuser to the child server.', [
                    'source_server_id' => $source->id,
                    'child_server_id' => $child->id,
                    'subuser_id' => $subuser->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }

    private function environmentFor(Server $server): array
    {
        $server->loadMissing('variables');

        /** @var EggVariable $variable */
        return $server->variables->mapWithKeys(function (EggVariable $variable) {
            return [$variable->env_variable => $variable->server_value ?? $variable->default_value ?? ''];
        })->all();
    }

    private function resolveRootServer(Server $server): Server
    {
        if (!$server->split_root_server_id) {
            return $server->loadMissing('allocation');
        }

        /** @var Server $root */
        $root = Server::query()->with('allocation')->findOrFail($server->split_root_server_id);

        return $root;
    }

    private function familyFor(Server $root): Collection
    {
        return Server::query()
            ->with(['allocation'])
            ->where('id', $root->id)
            ->orWhere('split_root_server_id', $root->id)
            ->orderBy('id')
            ->get();
    }

    private function serializeFamilyMember(Server $member, Server $current, Server $root): array
    {
        $member->loadMissing('allocation');

        $defaultAllocation = $member->allocation;
        $hasChildren = $member->splitChildServers()->exists();

        return [
            'id' => $member->id,
            'uuid' => $member->uuid,
            'identifier' => $member->identifier,
            'name' => $member->name,
            'memory' => $member->memory,
            'disk' => $member->disk,
            'cpu' => $member->cpu,
            'swap' => $member->swap,
            'isRoot' => $member->id === $root->id,
            'isCurrent' => $member->id === $current->id,
            'hasChildren' => $hasChildren,
            'canDelete' => $member->id !== $root->id && $member->id !== $current->id && !$hasChildren,
            'allocation' => [
                'ip' => $defaultAllocation?->ip,
                'alias' => $defaultAllocation?->alias,
                'port' => $defaultAllocation?->port,
                'label' => $defaultAllocation
                    ? sprintf('%s:%s', $defaultAllocation->alias ?: $defaultAllocation->ip, $defaultAllocation->port)
                    : 'No allocation',
            ],
        ];
    }
}
