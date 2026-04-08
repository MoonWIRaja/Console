<?php

namespace Pterodactyl\Models\Subdomains;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Pterodactyl\Models\Egg;
use Pterodactyl\Models\Model;
use Pterodactyl\Models\Nest;

class SubdomainRecord extends Model
{
    public const TYPE_CNAME = 'CNAME';
    public const TYPE_SRV = 'SRV';

    protected $table = 'subdomain_records';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'ttl' => 'integer',
        'proxied' => 'boolean',
        'priority' => 'integer',
        'weight' => 'integer',
    ];

    public static array $validationRules = [
        'domain_id' => 'required|integer|exists:subdomain_domains,id',
        'name' => 'required|string|max:191',
        'record_type' => 'required|string|in:CNAME,SRV',
        'ttl' => 'nullable|integer|min:60|max:86400',
        'proxied' => 'sometimes|boolean',
        'service' => 'nullable|string|max:64',
        'protocol' => 'nullable|string|max:16',
        'priority' => 'nullable|integer|min:0|max:65535',
        'weight' => 'nullable|integer|min:0|max:65535',
    ];

    public function domain(): BelongsTo
    {
        return $this->belongsTo(SubdomainDomain::class, 'domain_id');
    }

    public function eggs(): BelongsToMany
    {
        return $this->belongsToMany(Egg::class, 'subdomain_record_egg', 'subdomain_record_id', 'egg_id');
    }

    public function nests(): BelongsToMany
    {
        return $this->belongsToMany(Nest::class, 'subdomain_record_nest', 'subdomain_record_id', 'nest_id');
    }

    public function subdomains(): HasMany
    {
        return $this->hasMany(ServerSubdomain::class, 'subdomain_record_id');
    }
}
