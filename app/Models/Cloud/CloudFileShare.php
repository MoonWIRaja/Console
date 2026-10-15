<?php

namespace Pterodactyl\Models\Cloud;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CloudFileShare extends Model
{
    protected $table = 'cloud_file_shares';

    protected $guarded = ['id'];

    public function file(): BelongsTo
    {
        return $this->belongsTo(CloudFile::class, 'file_id');
    }
}
