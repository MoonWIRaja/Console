<?php

namespace Pterodactyl\Models\Discord;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Pterodactyl\Models\Model;
use Pterodactyl\Models\Server;

class ServerDiscordAgentEvent extends Model
{
    protected $table = 'server_discord_agent_events';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'payload' => 'array',
        'recorded_at' => 'datetime',
    ];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class, 'server_id');
    }
}
