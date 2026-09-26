<?php

namespace Pterodactyl\Models\Ai;

use Pterodactyl\Models\User;
use Pterodactyl\Models\Server;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiConversation extends Model
{
    protected $table = 'ai_conversations';

    protected $guarded = ['id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AiMessage::class, 'conversation_id')->orderBy('id');
    }

    public function pendingActions(): HasMany
    {
        return $this->hasMany(AiPendingAction::class, 'conversation_id');
    }
}
