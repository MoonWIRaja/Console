<?php

namespace Pterodactyl\Models\DownDetector;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Pterodactyl\Models\Model;

class DownDetectorMonitor extends Model
{
    public const STATUS_UNKNOWN = 'unknown';
    public const STATUS_UP = 'up';
    public const STATUS_DOWN = 'down';
    public const STATUS_IGNORED = 'ignored';

    protected $table = 'down_detector_monitors';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'last_meta' => 'array',
        'last_checked_at' => 'datetime',
        'last_status_changed_at' => 'datetime',
        'consecutive_failures' => 'integer',
        'consecutive_successes' => 'integer',
    ];

    public static array $validationRules = [
        'current_status' => 'required|string|max:32',
        'last_reason' => 'nullable|string|max:64',
        'last_message' => 'nullable|string',
        'last_meta' => 'nullable|array',
        'consecutive_failures' => 'required|integer|min:0',
        'consecutive_successes' => 'required|integer|min:0',
    ];

    public function monitorable(): MorphTo
    {
        return $this->morphTo();
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(DownDetectorIncident::class, 'monitor_id');
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->monitorable_type) {
            \Pterodactyl\Models\Node::class => 'node',
            \Pterodactyl\Models\Server::class => 'server',
            default => 'unknown',
        };
    }
}
