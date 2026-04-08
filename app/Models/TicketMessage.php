<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TicketMessage extends Model
{
    /** @use HasFactory<\Database\Factories\TicketMessageFactory> */
    use HasFactory;

    public const RESOURCE_NAME = 'ticket_message';

    public const AUTHOR_USER = 'user';
    public const AUTHOR_ADMIN = 'admin';
    public const AUTHOR_SYSTEM = 'system';

    public const ORIGIN_CONSOLE = 'console';
    public const ORIGIN_DISCORD = 'discord';
    public const ORIGIN_AUTOMATION = 'automation';
    public const ORIGIN_CHECKOUT = 'checkout';

    public const DISCORD_SYNC_PENDING = 'pending';
    public const DISCORD_SYNC_SYNCED = 'synced';
    public const DISCORD_SYNC_FAILED = 'failed';
    public const DISCORD_SYNC_SKIPPED = 'skipped';

    protected $table = 'ticket_messages';

    protected $fillable = [
        'ticket_id',
        'author_type',
        'author_user_id',
        'author_display_name',
        'author_avatar_url',
        'origin',
        'body',
        'discord_message_id',
        'discord_sync_status',
        'discord_synced_at',
        'discord_sync_error',
        'edited_at',
        'deleted_at',
        'meta',
    ];

    protected $casts = [
        'ticket_id' => 'integer',
        'author_user_id' => 'integer',
        'discord_synced_at' => 'datetime',
        'edited_at' => 'datetime',
        'deleted_at' => 'datetime',
        'meta' => 'array',
    ];

    public static array $validationRules = [
        'ticket_id' => 'required|exists:tickets,id',
        'author_type' => 'required|string|max:16',
        'author_user_id' => 'nullable|exists:users,id',
        'author_display_name' => 'nullable|string|max:191',
        'author_avatar_url' => 'nullable|string|max:2048',
        'origin' => 'required|string|max:32',
        'body' => 'nullable|string',
        'discord_message_id' => 'nullable|string|max:32',
        'discord_sync_status' => 'nullable|string|max:32',
    ];

    public function getRouteKeyName(): string
    {
        return 'id';
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TicketAttachment::class);
    }
}
