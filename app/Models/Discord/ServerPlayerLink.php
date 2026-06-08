<?php

namespace Pterodactyl\Models\Discord;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Pterodactyl\Models\Model;
use Pterodactyl\Models\Server;

class ServerPlayerLink extends Model
{
    protected $table = 'server_player_links';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'linked_at' => 'datetime',
    ];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class, 'server_id');
    }
}
