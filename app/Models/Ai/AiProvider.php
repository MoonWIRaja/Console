<?php

namespace Pterodactyl\Models\Ai;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiProvider extends Model
{
    protected $table = 'ai_providers';

    protected $guarded = ['id'];

    protected $hidden = ['api_key', 'credentials'];

    protected $casts = [
        'api_key' => 'encrypted',
        'credentials' => 'encrypted:array',
        'settings' => 'array',
        'token_expires_at' => 'datetime',
        'enabled' => 'boolean',
        'priority' => 'integer',
        'models_synced_at' => 'datetime',
    ];

    public function definition(): ?array
    {
        return \Pterodactyl\Services\Ai\Catalog\ProviderCatalog::get($this->preset);
    }

    public function models(): HasMany
    {
        return $this->hasMany(AiModel::class, 'provider_id');
    }
}
