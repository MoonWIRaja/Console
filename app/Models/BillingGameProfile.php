<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BillingGameProfile extends Model
{
    /** @use HasFactory<\Database\Factories\BillingGameProfileFactory> */
    use HasFactory;

    protected $table = 'billing_game_profiles';

    protected $fillable = [
        'billing_node_config_id',
        'egg_id',
        'display_name',
        'description',
        'docker_image',
        'startup',
        'environment',
        'enabled',
        'position',
    ];

    protected $casts = [
        'billing_node_config_id' => 'integer',
        'egg_id' => 'integer',
        'environment' => 'array',
        'enabled' => 'boolean',
        'position' => 'integer',
    ];

    public static array $validationRules = [
        'billing_node_config_id' => 'required|exists:billing_node_configs,id',
        'egg_id' => 'required|exists:eggs,id',
        'display_name' => 'required|string|max:191',
        'description' => 'nullable|string',
        'docker_image' => 'nullable|string|max:191',
        'startup' => 'nullable|string',
        'environment' => 'nullable|array',
        'enabled' => 'boolean',
        'position' => 'required|integer|min:0',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\Pterodactyl\Models\BillingNodeConfig, $this>
     */
    public function nodeConfig(): BelongsTo
    {
        return $this->belongsTo(BillingNodeConfig::class, 'billing_node_config_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\Pterodactyl\Models\Egg, $this>
     */
    public function egg(): BelongsTo
    {
        return $this->belongsTo(Egg::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\Pterodactyl\Models\BillingOrder, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(BillingOrder::class);
    }
}
