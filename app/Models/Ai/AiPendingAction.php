<?php

namespace Pterodactyl\Models\Ai;

use Pterodactyl\Models\Server;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiPendingAction extends Model
{
    protected $table = 'ai_pending_actions';

    protected $guarded = ['id'];

    protected $casts = ['arguments' => 'array'];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'conversation_id');
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
