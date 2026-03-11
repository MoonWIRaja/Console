<?php

namespace Pterodactyl\Services\Billing;

use Illuminate\Support\Arr;
use Pterodactyl\Models\Egg;
use Pterodactyl\Models\User;
use Pterodactyl\Models\BillingOrder;
use Pterodactyl\Models\BillingNodeConfig;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Models\BillingGameProfile;
use Pterodactyl\Services\Servers\VariableValidatorService;

class BillingOrderCreationService
{
    public function __construct(
        private BillingCatalogService $catalogService,
        private VariableValidatorService $variableValidatorService,
    ) {
    }

    public function handle(User $user, array $data, array $attributes = []): BillingOrder
    {
        $prepared = $this->prepareDraft($user, $data);

        return BillingOrder::query()->create(array_merge([
            'user_id' => $user->id,
            'billing_node_config_id' => $prepared['config']->id,
            'billing_game_profile_id' => $prepared['profile']->id,
            'node_id' => $prepared['config']->node_id,
            'egg_id' => $prepared['egg']->id,
            'status' => BillingOrder::STATUS_PENDING,
            'order_type' => BillingOrder::TYPE_NEW_SERVER,
            'server_name' => $prepared['server_name'],
            'node_name' => $prepared['config']->display_name ?: $prepared['config']->node->name,
            'game_name' => $prepared['profile']->display_name ?: $prepared['egg']->name,
            'cpu_cores' => $prepared['cpu_cores'],
            'memory_gb' => $prepared['memory_gb'],
            'disk_gb' => $prepared['disk_gb'],
            'price_per_vcore' => $prepared['config']->price_per_vcore,
            'price_per_gb_ram' => $prepared['config']->price_per_gb_ram,
            'price_per_10gb_disk' => $prepared['config']->price_per_10gb_disk,
            'cpu_total' => $prepared['pricing']['cpu_total'],
            'memory_total' => $prepared['pricing']['memory_total'],
            'disk_total' => $prepared['pricing']['disk_total'],
            'total' => $prepared['pricing']['total'],
            'docker_image' => $prepared['profile']->docker_image,
            'startup' => $prepared['profile']->startup,
            'environment' => $prepared['environment'],
            'allocation_limit' => $prepared['config']->default_allocation_limit,
            'database_limit' => $prepared['config']->default_database_limit,
            'backup_limit' => $prepared['config']->default_backup_limit,
            'swap' => $prepared['config']->default_swap,
            'io' => $prepared['config']->default_io,
            'oom_disabled' => $prepared['config']->default_oom_disabled,
            'start_on_completion' => $prepared['config']->start_on_completion,
        ], $attributes));
    }

    public function prepareDraft(User $user, array $data): array
    {
        /** @var BillingNodeConfig $config */
        $config = BillingNodeConfig::query()
            ->with(['node', 'gameProfiles.egg.variables'])
            ->findOrFail($data['billing_node_config_id']);

        /** @var BillingGameProfile|null $profile */
        $profile = $config->gameProfiles
            ->first(fn (BillingGameProfile $gameProfile) => $gameProfile->id === (int) $data['billing_game_profile_id']);

        if (!$config->enabled || !$profile || !$profile->enabled) {
            throw new DisplayException('The selected billing profile is no longer available.');
        }

        $availability = $this->catalogService->getAvailability($config);
        if (!$availability['is_available']) {
            throw new DisplayException('This billing node is currently sold out or unavailable.');
        }

        $cpuCores = max((int) $data['cpu_cores'], 0);
        $memoryGb = max((int) $data['memory_gb'], 0);
        $diskGb = max((int) $data['disk_gb'], 0);

        if ($config->cpu_stock < 1) {
            throw new DisplayException('This billing node does not have a vCore limit configured yet.');
        }

        if ($cpuCores < 1 || $cpuCores > $config->cpu_stock) {
            throw new DisplayException(sprintf(
                'vCore selection must be between 1 and %d for this node.',
                $config->cpu_stock
            ));
        }

        if ($memoryGb < 1 || $memoryGb > $availability['memory_remaining_gb']) {
            throw new DisplayException(sprintf(
                'RAM selection must be between 1 GB and %d GB for this node.',
                $availability['memory_remaining_gb']
            ));
        }

        if ($diskGb < BillingCatalogService::DISK_STEP_GB || $diskGb > $availability['disk_remaining_gb']) {
            throw new DisplayException(sprintf(
                'Storage selection must be between %d GB and %d GB for this node.',
                BillingCatalogService::DISK_STEP_GB,
                $availability['disk_remaining_gb']
            ));
        }

        if ($diskGb % BillingCatalogService::DISK_STEP_GB !== 0) {
            throw new DisplayException(sprintf(
                'Storage must be selected in %d GB steps.',
                BillingCatalogService::DISK_STEP_GB
            ));
        }

        $egg = $profile->egg;
        $environment = $this->resolveEnvironment($profile, $egg, Arr::wrap($data['variables'] ?? []));
        $this->variableValidatorService
            ->setUserLevel(User::USER_LEVEL_ADMIN)
            ->handle($egg->id, $environment);

        $pricing = $this->catalogService->calculatePricing($config, $cpuCores, $memoryGb, $diskGb);

        return [
            'user' => $user,
            'config' => $config,
            'profile' => $profile,
            'egg' => $egg,
            'server_name' => trim((string) $data['server_name']),
            'cpu_cores' => $cpuCores,
            'memory_gb' => $memoryGb,
            'disk_gb' => $diskGb,
            'environment' => $environment,
            'pricing' => $pricing,
        ];
    }

    private function resolveEnvironment(BillingGameProfile $profile, Egg $egg, array $requestedVariables): array
    {
        $environment = collect($profile->environment ?? [])
            ->mapWithKeys(fn ($value, $key) => [$key => is_scalar($value) ? (string) $value : ''])
            ->all();

        foreach ($egg->variables as $variable) {
            if (!array_key_exists($variable->env_variable, $environment) || $environment[$variable->env_variable] === '') {
                $environment[$variable->env_variable] = (string) ($variable->default_value ?? '');
            }
        }

        foreach ($egg->variables as $variable) {
            if (!$variable->user_viewable || !$variable->user_editable) {
                continue;
            }

            if (array_key_exists($variable->env_variable, $requestedVariables)) {
                $environment[$variable->env_variable] = (string) ($requestedVariables[$variable->env_variable] ?? '');
            }
        }

        return $environment;
    }
}
