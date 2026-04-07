<?php

namespace Pterodactyl\Models\Security;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Pterodactyl\Models\Model;
use Pterodactyl\Models\Node;

class SecurityIncident extends Model
{
    protected $table = 'security_incidents';

    protected $guarded = ['id'];

    protected $casts = [
        'confidence' => 'int',
        'blocked' => 'bool',
        'evidence' => 'array',
        'meta' => 'array',
        'event_count' => 'int',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public static array $validationRules = [
        'threat_id' => 'required|string|max:64|unique:security_incidents,threat_id',
        'title' => 'required|string|max:191',
        'summary' => 'nullable|string',
        'class' => 'required|string|max:64',
        'surface' => 'required|string|max:64',
        'status' => 'required|string|max:32',
        'severity' => 'required|string|max:16',
        'confidence' => 'required|integer|min:0|max:100',
        'source_ip' => 'nullable|string|max:64',
        'fingerprint' => 'nullable|string|max:64',
        'actor_type' => 'nullable|string|max:191',
        'actor_id' => 'nullable|integer',
        'node_id' => 'nullable|integer|exists:nodes,id',
        'target_type' => 'nullable|string|max:191',
        'target_id' => 'nullable|integer',
        'evidence' => 'nullable|array',
        'meta' => 'nullable|array',
        'verdict' => 'required|string|max:32',
        'blocked' => 'required|boolean',
        'mitigation_stage' => 'required|string|max:32',
        'correlation_id' => 'nullable|string|max:128',
        'event_count' => 'required|integer|min:1',
        'first_seen_at' => 'nullable|date',
        'last_seen_at' => 'nullable|date',
        'resolved_at' => 'nullable|date',
    ];

    public function actor(): MorphTo
    {
        return $this->morphTo();
    }

    public function target(): MorphTo
    {
        return $this->morphTo();
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(SecurityEvent::class, 'incident_id');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(SecurityAction::class, 'incident_id');
    }

    public function quarantineArtifacts(): HasMany
    {
        return $this->hasMany(SecurityQuarantineArtifact::class, 'incident_id');
    }
}
