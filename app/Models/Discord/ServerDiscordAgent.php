<?php

namespace Pterodactyl\Models\Discord;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Pterodactyl\Models\Model;
use Pterodactyl\Models\Server;

class ServerDiscordAgent extends Model
{
    public const INSTALL_NOT_INSTALLED = 'not_installed';
    public const INSTALL_INSTALLED = 'installed';
    public const INSTALL_NEEDS_RESTART = 'needs_restart';
    public const INSTALL_ERROR = 'error';

    public const CONNECTION_OFFLINE = 'offline';
    public const CONNECTION_CONNECTED = 'connected';
    public const CONNECTION_STALE = 'stale';

    protected $table = 'server_discord_agents';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $hidden = ['agent_secret_encrypted'];

    protected $casts = [
        'detection_confidence' => 'integer',
        'detection_sources' => 'array',
        'capabilities' => 'array',
        'last_fingerprint' => 'array',
        'runtime_state' => 'array',
        'installed_at' => 'datetime',
        'restart_requested_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'last_sync_at' => 'datetime',
    ];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class, 'server_id');
    }
}
