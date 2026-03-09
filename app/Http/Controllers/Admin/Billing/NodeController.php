<?php

namespace Pterodactyl\Http\Controllers\Admin\Billing;

use Illuminate\View\View;
use Pterodactyl\Models\Nest;
use Pterodactyl\Models\Egg;
use Pterodactyl\Models\Node;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\BillingOrder;
use Pterodactyl\Models\User;
use Pterodactyl\Models\BillingGameProfile;
use Pterodactyl\Models\BillingNodeConfig;
use Pterodactyl\Models\EggVariable;
use Pterodactyl\Services\Billing\BillingCatalogService;
use Pterodactyl\Http\Requests\Admin\Billing\BillingNodeConfigFormRequest;
use Pterodactyl\Http\Requests\Admin\Billing\BillingGameProfileFormRequest;
use Pterodactyl\Http\Requests\Admin\Billing\BillingGameProfileUpdateRequest;
use Pterodactyl\Services\Servers\VariableValidatorService;

class NodeController extends Controller
{
    public function __construct(
        private AlertsMessageBag $alert,
        private BillingCatalogService $catalogService,
        private VariableValidatorService $variableValidatorService,
    ) {
    }

    public function view(Node $node): View
    {
        $config = $this->getOrCreateConfig($node);
        $config->load(['node.location', 'gameProfiles.egg.nest']);

        return view('admin.billing.node', [
            'node' => $node,
            'config' => $config,
            'availability' => $this->catalogService->getAvailability($config),
            'nests' => Nest::query()->withCount('eggs')->orderBy('name')->get(),
            'selectedNestIds' => $config->gameProfiles
                ->map(fn (BillingGameProfile $profile) => (int) $profile->egg->nest_id)
                ->unique()
                ->values()
                ->all(),
            'recentOrders' => BillingOrder::query()
                ->with(['user', 'server'])
                ->where('billing_node_config_id', $config->id)
                ->latest()
                ->limit(20)
                ->get(),
        ]);
    }

    public function update(BillingNodeConfigFormRequest $request, Node $node): RedirectResponse
    {
        $config = $this->getOrCreateConfig($node);
        $config->forceFill([
            'enabled' => $request->boolean('enabled'),
            'display_name' => $request->input('display_name'),
            'description' => $request->input('description'),
            'cpu_stock' => $request->integer('cpu_stock'),
            'memory_stock_gb' => $request->integer('memory_stock_gb'),
            'disk_stock_gb' => $request->integer('disk_stock_gb'),
            'show_remaining_capacity' => $request->boolean('show_remaining_capacity'),
            'price_per_vcore' => $request->input('price_per_vcore'),
            'price_per_gb_ram' => $request->input('price_per_gb_ram'),
            'price_per_10gb_disk' => $request->input('price_per_10gb_disk'),
            'default_allocation_limit' => $request->integer('default_allocation_limit'),
            'default_database_limit' => $request->integer('default_database_limit'),
            'default_backup_limit' => $request->integer('default_backup_limit'),
            'default_swap' => $request->integer('default_swap'),
            'default_io' => $request->integer('default_io'),
            'default_oom_disabled' => $request->boolean('default_oom_disabled'),
            'start_on_completion' => $request->boolean('start_on_completion'),
        ])->saveOrFail();

        $this->alert->success('Billing configuration for this node has been updated.')->flash();

        return redirect()->route('admin.billing.nodes.view', $node->id);
    }

    public function storeGameProfile(BillingGameProfileFormRequest $request, Node $node): RedirectResponse
    {
        $config = $this->getOrCreateConfig($node);
        $selectedNestIds = $request->nestIds();
        $nests = Nest::query()
            ->with(['eggs.variables'])
            ->whereIn('id', $selectedNestIds)
            ->orderBy('name')
            ->get();

        $desiredEggs = $nests
            ->flatMap(fn (Nest $nest) => $nest->eggs)
            ->sortBy('name')
            ->values();
        $desiredEggIds = $desiredEggs->map(fn (Egg $egg) => (int) $egg->id)->all();
        $existingProfiles = $config->gameProfiles()->get()->keyBy(fn (BillingGameProfile $profile) => (int) $profile->egg_id);

        if (empty($desiredEggIds)) {
            $config->gameProfiles()->delete();
            $this->alert->success('Billing nests have been cleared. No eggs are currently exposed on this node.')->flash();

            return redirect()->route('admin.billing.nodes.view', $node->id);
        }

        $config->gameProfiles()
            ->whereNotIn('egg_id', $desiredEggIds)
            ->delete();

        foreach ($desiredEggs as $position => $egg) {
            $payload = [
                'display_name' => $egg->name,
                'description' => $egg->description,
                'docker_image' => null,
                'startup' => null,
                'environment' => $this->resolveDefaultEnvironment($egg),
                'enabled' => true,
                'position' => $position,
            ];

            /** @var BillingGameProfile|null $profile */
            $profile = $existingProfiles->get((int) $egg->id);
            if ($profile) {
                $profile->forceFill($payload)->saveOrFail();

                continue;
            }

            $config->gameProfiles()->create(array_merge($payload, [
                'egg_id' => $egg->id,
            ]));
        }

        $this->alert->success(sprintf(
            'Billing nest selection has been updated. %d nest(s) now expose %d egg(s) on this node.',
            count($selectedNestIds),
            count($desiredEggIds)
        ))->flash();

        return redirect()->route('admin.billing.nodes.view', $node->id);
    }

    public function updateGameProfile(BillingGameProfileUpdateRequest $request, Node $node, BillingGameProfile $billingGameProfile): RedirectResponse
    {
        $config = $this->getOrCreateConfig($node);
        if ($billingGameProfile->billing_node_config_id !== $config->id) {
            abort(404);
        }

        $billingGameProfile->forceFill([
            'enabled' => $request->boolean('enabled'),
        ])->saveOrFail();

        $this->alert->success('The billing game profile status has been updated.')->flash();

        return redirect()->route('admin.billing.nodes.view', $node->id);
    }

    public function deleteGameProfile(Node $node, BillingGameProfile $billingGameProfile): RedirectResponse
    {
        $config = $this->getOrCreateConfig($node);
        if ($billingGameProfile->billing_node_config_id !== $config->id) {
            abort(404);
        }

        $billingGameProfile->delete();

        $this->alert->success('The billing game profile has been removed.')->flash();

        return redirect()->route('admin.billing.nodes.view', $node->id);
    }

    private function resolveDefaultEnvironment(Egg $egg): array
    {
        $environment = $egg->variables
            ->mapWithKeys(function (EggVariable $variable) {
                $value = (string) ($variable->default_value ?? '');
                if ($value === '' && $variable->required) {
                    $value = $this->resolveFallbackVariableValue($variable);
                }

                return [$variable->env_variable => $value];
            })
            ->all();

        try {
            $this->variableValidatorService
                ->setUserLevel(User::USER_LEVEL_ADMIN)
                ->handle($egg->id, $environment);
        } catch (\Illuminate\Validation\ValidationException) {
            // Keep the best-effort defaults so every egg under a selected nest can still be listed.
        }

        return $environment;
    }

    private function resolveFallbackVariableValue(EggVariable $variable): string
    {
        $identifier = strtoupper($variable->env_variable . ' ' . $variable->name);
        $rules = strtolower($variable->rules);

        if (Str::contains($identifier, ['PASSWORD', 'PASS', 'TOKEN', 'SECRET', 'KEY'])) {
            return Str::random(16);
        }

        if (Str::contains($identifier, ['EMAIL'])) {
            return 'admin@example.com';
        }

        if (Str::contains($identifier, ['USER', 'USERNAME', 'ADMIN'])) {
            return 'admin';
        }

        if (Str::contains($identifier, ['IP', 'HOST'])) {
            return '127.0.0.1';
        }

        if (Str::contains($identifier, ['PORT'])) {
            return '25565';
        }

        if (Str::contains($rules, ['integer', 'numeric', 'digits'])) {
            return '1';
        }

        if (Str::contains($rules, ['boolean'])) {
            return '1';
        }

        return 'billing';
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
                'default_swap' => 0,
                'default_io' => 500,
                'default_oom_disabled' => true,
                'start_on_completion' => true,
            ]
        );
    }
}
