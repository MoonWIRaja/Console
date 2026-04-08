<?php

namespace Pterodactyl\Models\Subdomains;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Pterodactyl\Models\Model;

class SubdomainDomain extends Model
{
    public const PROVIDER_CLOUDFLARE = 'cloudflare';

    protected $table = 'subdomain_domains';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $hidden = ['api_token'];

    public static array $validationRules = [
        'name' => 'required|string|max:191|unique:subdomain_domains,name',
        'provider' => 'required|string|in:cloudflare',
        'api_token' => 'required|string',
        'zone_identifier' => 'required|string|max:191',
    ];

    public function records(): HasMany
    {
        return $this->hasMany(SubdomainRecord::class, 'domain_id');
    }

    public function subdomains(): HasMany
    {
        return $this->hasMany(ServerSubdomain::class, 'domain_id');
    }

    public function getDecryptedApiToken(): string
    {
        return decrypt($this->getRawOriginal('api_token'));
    }
}
