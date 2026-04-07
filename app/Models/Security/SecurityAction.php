<?php

namespace Pterodactyl\Models\Security;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Pterodactyl\Models\Model;

class SecurityAction extends Model
{
    protected $table = 'security_actions';

    protected $guarded = ['id'];

    protected $casts = [
        'payload' => 'array',
        'result' => 'array',
        'execute_after' => 'datetime',
        'acknowledged_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public static array $validationRules = [
        'agent_id' => 'nullable|integer|exists:security_agents,id',
        'incident_id' => 'nullable|integer|exists:security_incidents,id',
        'event_id' => 'nullable|integer|exists:security_events,id',
        'action' => 'required|string|max:64',
        'scope' => 'nullable|string|max:64',
        'target_type' => 'nullable|string|max:191',
        'target_id' => 'nullable|integer',
        'source_ip' => 'nullable|string|max:64',
        'fingerprint' => 'nullable|string|max:64',
        'payload' => 'nullable|array',
        'status' => 'required|string|max:32',
        'result' => 'nullable|array',
        'execute_after' => 'nullable|date',
        'acknowledged_at' => 'nullable|date',
        'completed_at' => 'nullable|date',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(SecurityAgent::class, 'agent_id');
    }

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
