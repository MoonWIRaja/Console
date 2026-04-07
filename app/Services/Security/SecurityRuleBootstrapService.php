<?php

namespace Pterodactyl\Services\Security;

use Illuminate\Support\Collection;
use Pterodactyl\Models\Security\SecurityRule;
use Pterodactyl\Services\Security\Rules\SecurityRuleCatalog;

class SecurityRuleBootstrapService
{
    private bool $bootstrapped = false;

    public function syncDefaults(): Collection
    {
        if ($this->bootstrapped) {
            return SecurityRule::query()->orderBy('surface')->orderBy('name')->get();
        }

        foreach (SecurityRuleCatalog::defaults() as $rule) {
            SecurityRule::query()->firstOrCreate(
                ['key' => $rule['key']],
                $rule
            );
        }

        $this->bootstrapped = true;

        return SecurityRule::query()->orderBy('surface')->orderBy('name')->get();
    }

    public function get(string $key): SecurityRule
    {
        $this->syncDefaults();

        $rule = SecurityRule::query()->where('key', $key)->first();
        if ($rule) {
            return $rule;
        }

        $surface = str_contains($key, 'agent') ? 'agent' : (str_contains($key, 'upload') ? 'upload' : 'runtime');

        return SecurityRule::query()->create([
            'key' => $key,
            'name' => ucwords(str_replace(['_', '-'], ' ', $key)),
            'description' => 'Dynamically created placeholder rule for a custom Security Center signal.',
            'class' => 'custom_signal',
            'surface' => $surface,
            'enabled' => true,
            'mode' => 'active',
            'threshold' => 10,
            'window_seconds' => 300,
            'weight' => 10,
            'response_policy' => [],
            'cooldown_seconds' => 300,
            'agent_required' => $surface === 'agent',
        ]);
    }
}
