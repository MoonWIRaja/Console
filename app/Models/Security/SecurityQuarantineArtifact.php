<?php

namespace Pterodactyl\Models\Security;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Pterodactyl\Models\Model;

class SecurityQuarantineArtifact extends Model
{
    protected $table = 'security_quarantine_artifacts';

    protected $guarded = ['id'];

    protected $casts = [
        'meta' => 'array',
        'quarantined_at' => 'datetime',
        'released_at' => 'datetime',
    ];

    public static array $validationRules = [
        'incident_id' => 'nullable|integer|exists:security_incidents,id',
        'event_id' => 'nullable|integer|exists:security_events,id',
        'target_type' => 'required|string|max:191',
        'target_id' => 'required|integer',
        'disk' => 'nullable|string|max:64',
        'path' => 'nullable|string',
        'original_name' => 'nullable|string|max:191',
        'sha256' => 'nullable|string|max:128',
        'reason' => 'required|string|max:191',
        'status' => 'required|string|max:32',
        'meta' => 'nullable|array',
        'quarantined_at' => 'nullable|date',
        'released_at' => 'nullable|date',
    ];

    public function incident(): BelongsTo
    {
        return $this->belongsTo(SecurityIncident::class, 'incident_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(SecurityEvent::class, 'event_id');
    }

    public function target(): MorphTo
    {
        return $this->morphTo();
    }
}
