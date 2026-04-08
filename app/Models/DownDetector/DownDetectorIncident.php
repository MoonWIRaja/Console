<?php

namespace Pterodactyl\Models\DownDetector;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Pterodactyl\Models\Model;

class DownDetectorIncident extends Model
{
    public $timestamps = false;

    protected $table = 'down_detector_incidents';

    protected $guarded = ['id', 'created_at'];

    protected $casts = [
        'meta' => 'array',
        'created_at' => 'datetime',
    ];

    public static array $validationRules = [
        'monitor_id' => 'required|integer|exists:down_detector_monitors,id',
        'from_status' => 'nullable|string|max:32',
        'to_status' => 'required|string|max:32',
        'reason' => 'nullable|string|max:64',
        'summary' => 'nullable|string',
        'meta' => 'nullable|array',
    ];

    public function monitor(): BelongsTo
    {
        return $this->belongsTo(DownDetectorMonitor::class, 'monitor_id');
    }
}
