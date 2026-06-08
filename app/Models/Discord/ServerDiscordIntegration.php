<?php

namespace Pterodactyl\Models\Discord;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Pterodactyl\Models\Model;
use Pterodactyl\Models\Server;

class ServerDiscordIntegration extends Model
{
    protected $table = 'server_discord_integrations';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'enabled' => 'boolean',
        'chat_bridge_enabled' => 'boolean',
        'console_bridge_enabled' => 'boolean',
        'linking_enabled' => 'boolean',
        'whitelist_requires_link' => 'boolean',
        'features' => 'array',
        'enabled_at' => 'datetime',
    ];

    protected $hidden = ['bot_token_encrypted'];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class, 'server_id');
    }

    public function hasBotToken(): bool
    {
        return filled($this->bot_token_encrypted);
    }
}
