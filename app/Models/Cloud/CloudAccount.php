<?php

namespace Pterodactyl\Models\Cloud;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CloudAccount extends Model
{
    protected $table = 'cloud_accounts';

    protected $guarded = ['id'];

    protected $hidden = ['access_token', 'refresh_token'];

    protected $casts = [
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'token_expires_at' => 'datetime',
        'quota_checked_at' => 'datetime',
        'enabled' => 'boolean',
        'quota_limit' => 'integer',
        'quota_usage' => 'integer',
    ];

    public function files(): HasMany
    {
        return $this->hasMany(CloudFile::class, 'account_id');
    }

    public function freeBytes(): ?int
    {
        return $this->quota_limit === null ? null : max(0, $this->quota_limit - $this->quota_usage);
    }
}
