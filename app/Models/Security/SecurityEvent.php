<?php

namespace Pterodactyl\Models\Security;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Pterodactyl\Models\Model;
use Pterodactyl\Models\Node;

class SecurityEvent extends Model
{
    protected $table = 'security_events';

    protected $guarded = ['id'];

    protected $casts = [
        'confidence' => 'int',
        'blocked' => 'bool',
        'evidence' => 'array',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public static array $validationRules = [
        'incident_id' => 'nullable|integer|exists:security_incidents,id',
        'rule_id' => 'nullable|integer|exists:security_rules,id',
        'threat_id' => 'required|string|max:64',
        'class' => 'required|string|max:64',
        'surface' => 'required|string|max:64',
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
        'verdict' => 'required|string|max:32',
        'blocked' => 'required|boolean',
        'mitigation_stage' => 'required|string|max:32',
        'correlation_id' => 'nullable|string|max:128',
        'first_seen_at' => 'nullable|date',
        'last_seen_at' => 'nullable|date',
    ];

    public function incident(): BelongsTo
    {
        return $this->belongsTo(SecurityIncident::class, 'incident_id');
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(SecurityRule::class, 'rule_id');
    }

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

    public function actions(): HasMany
    {
        return $this->hasMany(SecurityAction::class, 'event_id');
    }
}
