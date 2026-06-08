<?php

namespace Pterodactyl\Models\Discord;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Pterodactyl\Models\Model;
use Pterodactyl\Models\Server;

class ServerPlayerSnapshot extends Model
{
    protected $table = 'server_player_snapshots';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'ping' => 'integer',
        'is_operator' => 'boolean',
        'is_admin' => 'boolean',
        'banned' => 'boolean',
        'meta' => 'array',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class, 'server_id');
    }
}
