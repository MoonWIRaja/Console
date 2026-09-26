<?php

namespace Pterodactyl\Models\Ai;

use Illuminate\Database\Eloquent\Model;

class AiSkill extends Model
{
    public const SCOPES = ['both', 'panel', 'discord'];

    protected $table = 'ai_skills';

    protected $guarded = ['id'];

    protected $casts = ['enabled' => 'boolean'];
}
