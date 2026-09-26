<?php

namespace Pterodactyl\Models\Ai;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiModel extends Model
{
    protected $table = 'ai_models';

    protected $guarded = ['id'];

    protected $casts = [
        'enabled' => 'boolean',
        'is_default' => 'boolean',
        'supports_tools' => 'boolean',
        'priority' => 'integer',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(AiProvider::class, 'provider_id');
    }

    public function displayName(): string
    {
        return $this->label ?: $this->model_id;
    }

    public static function usable()
    {
        return static::query()
            ->with('provider')
            ->where('enabled', true)
            ->whereHas('provider', fn ($q) => $q->where('enabled', true))
            ->orderByDesc('is_default')
            ->orderBy('priority')
            ->orderBy('id');
    }
}
