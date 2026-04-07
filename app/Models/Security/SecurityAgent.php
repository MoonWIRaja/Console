<?php

namespace Pterodactyl\Models\Security;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Pterodactyl\Models\Model;
use Pterodactyl\Models\Node;

class SecurityAgent extends Model
{
    protected $table = 'security_agents';

    protected $guarded = ['id'];

    protected $casts = [
        'capabilities' => 'array',
        'meta' => 'array',
        'secret_rotated_at' => 'datetime',
        'last_heartbeat_at' => 'datetime',
        'last_reported_at' => 'datetime',
        'isolated_at' => 'datetime',
    ];

    public static array $validationRules = [
        'uuid' => 'required|string|max:36|unique:security_agents,uuid',
        'name' => 'required|string|max:191',
        'node_id' => 'nullable|integer|exists:nodes,id',
        'status' => 'required|string|max:32',
        'capabilities' => 'nullable|array',
        'current_secret_encrypted' => 'nullable|string',
        'previous_secret_encrypted' => 'nullable|string',
        'secret_rotated_at' => 'nullable|date',
        'last_heartbeat_at' => 'nullable|date',
        'last_reported_at' => 'nullable|date',
        'isolated_at' => 'nullable|date',
        'last_ip' => 'nullable|string|max:64',
        'meta' => 'nullable|array',
    ];

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }

    public function actions(): HasMany
    {
        return $this->hasMany(SecurityAction::class, 'agent_id');
    }
}
