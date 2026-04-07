<?php

namespace Pterodactyl\Models\Subdomains;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Pterodactyl\Models\Model;
use Pterodactyl\Models\Server;

class ServerSubdomain extends Model
{
    protected $table = 'server_subdomains';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'provider_record_ids' => 'array',
    ];

    public static array $validationRules = [
        'server_id' => 'required|integer|exists:servers,id',
        'domain_id' => 'required|integer|exists:subdomain_domains,id',
        'subdomain_record_id' => 'required|integer|exists:subdomain_records,id',
        'hostname_label' => 'required|string|max:63',
        'full_domain' => 'required|string|max:191',
        'record_type' => 'required|string|in:CNAME,SRV',
        'resolved_target' => 'nullable|string|max:191',
    ];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class, 'server_id');
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(SubdomainDomain::class, 'domain_id');
    }

    public function record(): BelongsTo
    {
        return $this->belongsTo(SubdomainRecord::class, 'subdomain_record_id');
    }
}
