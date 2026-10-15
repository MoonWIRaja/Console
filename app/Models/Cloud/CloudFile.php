<?php

namespace Pterodactyl\Models\Cloud;

use Pterodactyl\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CloudFile extends Model
{
    public const SOURCE_UPLOAD = 'upload';
    public const SOURCE_ANNEY = 'anney';

    protected $table = 'cloud_files';

    protected $guarded = ['id'];

    protected $casts = [
        'size' => 'integer',
        'shared_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(CloudAccount::class, 'account_id');
    }

    public function shares(): HasMany
    {
        return $this->hasMany(CloudFileShare::class, 'file_id');
    }
}
