<?php

namespace Pterodactyl\Models\Ai;

use Illuminate\Database\Eloquent\Model;

class AiMessage extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'ai_messages';

    protected $guarded = ['id'];

    protected $casts = [
        'tool_calls' => 'array',
        'meta' => 'array',
    ];
}
