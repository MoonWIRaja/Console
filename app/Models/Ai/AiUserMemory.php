<?php

namespace Pterodactyl\Models\Ai;

use Illuminate\Database\Eloquent\Model;

class AiUserMemory extends Model
{
    protected $table = 'ai_user_memories';

    protected $guarded = ['id'];
}
