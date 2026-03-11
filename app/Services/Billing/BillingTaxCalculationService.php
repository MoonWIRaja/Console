<?php

namespace Pterodactyl\Services\Billing;

use Pterodactyl\Models\BillingTaxRule;

class BillingTaxCalculationService
{
    public function calculate(string $context, array $profileSnapshot, float $subtotal): array
    {
        $normalizedContext = match ($context) {
            'new_server', 'new_order' => 'new_order',
            'renewal' => 'renewal',
            'upgrade' => 'upgrade',
            default => 'new_order',
        };

        $countryCode = strtoupper((string) ($profileSnapshot['country_code'] ?? ''));
        $isBusiness = (bool) ($profileSnapshot['is_business'] ?? false);
        $hasTaxId = !empty($profileSnapshot['tax_id']);

        $rules = BillingTaxRule::query()
            ->where('is_active', true)
            ->orderBy('priority')
            ->get()
            ->filter(function (BillingTaxRule $rule) use ($normalizedContext, $countryCode, $isBusiness, $hasTaxId) {
                if ($rule->country_code && strtoupper($rule->country_code) !== $countryCode) {
                    return false;
                }

                if (!is_null($rule->is_business) && (bool) $rule->is_business !== $isBusiness) {
                    return false;
                }

                if (!is_null($rule->tax_id_required)) {
                    if ((bool) $rule->tax_id_required && !$hasTaxId) {
                        return false;
                    }

                    if (!(bool) $rule->tax_id_required && $hasTaxId) {
                        return false;
                    }
                }

                return match ($normalizedContext) {
                    'renewal' => $rule->apply_to_renewals,
                    'upgrade' => $rule->apply_to_upgrades,
                    default => $rule->apply_to_new_orders,
                };
            })
            ->values();

        $taxTotal = 0.0;
        $items = [];

        foreach ($rules as $rule) {
            $amount = $rule->rate_type === BillingTaxRule::RATE_TYPE_FIXED
                ? (float) $rule->rate_value
                : round($subtotal * ((float) $rule->rate_value / 100), 2);

            $amount = round(max($amount, 0), 2);
            $taxTotal += $amount;

            $items[] = [
                'rule_id' => $rule->id,
                'name' => $rule->name,
                'rate_type' => $rule->rate_type,
                'rate_value' => (float) $rule->rate_value,
                'amount' => $amount,
            ];
        }

        $taxTotal = round($taxTotal, 2);

        return [
            'items' => $items,
            'subtotal' => round($subtotal, 2),
            'tax_total' => $taxTotal,
            'grand_total' => round($subtotal + $taxTotal, 2),
        ];
    }
}
