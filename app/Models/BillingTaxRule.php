<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class BillingTaxRule extends Model
{
    /** @use HasFactory<\Database\Factories\BillingTaxRuleFactory> */
    use HasFactory;

    public const RATE_TYPE_PERCENTAGE = 'percentage';
    public const RATE_TYPE_FIXED = 'fixed';

    protected $table = 'billing_tax_rules';

    protected $fillable = [
        'name',
        'priority',
        'country_code',
        'is_business',
        'tax_id_required',
        'rate_type',
        'rate_value',
        'apply_to_new_orders',
        'apply_to_renewals',
        'apply_to_upgrades',
        'is_active',
    ];

    protected $casts = [
        'priority' => 'integer',
        'is_business' => 'boolean',
        'tax_id_required' => 'boolean',
        'rate_value' => 'decimal:4',
        'apply_to_new_orders' => 'boolean',
        'apply_to_renewals' => 'boolean',
        'apply_to_upgrades' => 'boolean',
        'is_active' => 'boolean',
    ];
}
