<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BillingNodeConfig extends Model
{
    /** @use HasFactory<\Database\Factories\BillingNodeConfigFactory> */
    use HasFactory;

    protected $table = 'billing_node_configs';

    protected $fillable = [
        'node_id',
        'enabled',
        'display_name',
        'description',
        'cpu_stock',
        'memory_stock_gb',
        'disk_stock_gb',
        'show_remaining_capacity',
        'price_per_vcore',
        'price_per_gb_ram',
        'price_per_10gb_disk',
        'default_allocation_limit',
        'default_database_limit',
        'default_backup_limit',
        'default_swap',
        'default_io',
        'default_oom_disabled',
        'start_on_completion',
    ];

    protected $casts = [
        'node_id' => 'integer',
        'enabled' => 'boolean',
        'cpu_stock' => 'integer',
        'memory_stock_gb' => 'integer',
        'disk_stock_gb' => 'integer',
        'show_remaining_capacity' => 'boolean',
        'price_per_vcore' => 'decimal:2',
        'price_per_gb_ram' => 'decimal:2',
        'price_per_10gb_disk' => 'decimal:2',
        'default_allocation_limit' => 'integer',
        'default_database_limit' => 'integer',
        'default_backup_limit' => 'integer',
        'default_swap' => 'integer',
        'default_io' => 'integer',
        'default_oom_disabled' => 'boolean',
        'start_on_completion' => 'boolean',
    ];

    public static array $validationRules = [
        'node_id' => 'required|exists:nodes,id|unique:billing_node_configs,node_id',
        'enabled' => 'boolean',
        'display_name' => 'required|string|max:191',
        'description' => 'nullable|string',
        'cpu_stock' => 'required|integer|min:0',
        'memory_stock_gb' => 'required|integer|min:0',
        'disk_stock_gb' => 'required|integer|min:0',
        'show_remaining_capacity' => 'boolean',
        'price_per_vcore' => 'required|numeric|min:0',
        'price_per_gb_ram' => 'required|numeric|min:0',
        'price_per_10gb_disk' => 'required|numeric|min:0',
        'default_allocation_limit' => 'required|integer|min:0',
        'default_database_limit' => 'required|integer|min:0',
        'default_backup_limit' => 'required|integer|min:0',
        'default_swap' => 'required|integer|min:-1',
        'default_io' => 'required|integer|between:10,1000',
        'default_oom_disabled' => 'boolean',
        'start_on_completion' => 'boolean',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\Pterodactyl\Models\Node, $this>
     */
    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\Pterodactyl\Models\BillingGameProfile, $this>
     */
    public function gameProfiles(): HasMany
    {
        return $this->hasMany(BillingGameProfile::class)->orderBy('position')->orderBy('display_name');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\Pterodactyl\Models\BillingOrder, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(BillingOrder::class);
    }
}
