<?php

namespace Pterodactyl\Models\Cloud;

use Illuminate\Database\Eloquent\Model;

class CloudUserFolder extends Model
{
    protected $table = 'cloud_user_folders';

    protected $guarded = ['id'];
}
