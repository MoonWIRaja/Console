<?php

namespace Pterodactyl\Models\Security;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Pterodactyl\Models\Model;

class SecurityRule extends Model
{
    protected $table = 'security_rules';

    protected $guarded = ['id'];

    protected $casts = [
        'enabled' => 'bool',
        'threshold' => 'int',
        'window_seconds' => 'int',
        'weight' => 'int',
        'response_policy' => 'array',
        'cooldown_seconds' => 'int',
        'agent_required' => 'bool',
    ];

    public static array $validationRules = [
        'key' => 'required|string|max:96|unique:security_rules,key',
        'name' => 'required|string|max:191',
        'description' => 'nullable|string',
        'class' => 'required|string|max:64',
        'surface' => 'required|string|max:64',
        'enabled' => 'required|boolean',
        'mode' => 'required|string|max:32',
        'threshold' => 'required|integer|min:1|max:100000',
        'window_seconds' => 'required|integer|min:1|max:86400',
        'weight' => 'required|integer|min:1|max:10000',
        'response_policy' => 'nullable|array',
        'cooldown_seconds' => 'required|integer|min:1|max:86400',
        'agent_required' => 'required|boolean',
    ];

    public function events(): HasMany
    {
        return $this->hasMany(SecurityEvent::class, 'rule_id');
    }
}
