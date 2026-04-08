<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TicketAttachment extends Model
{
    /** @use HasFactory<\Database\Factories\TicketAttachmentFactory> */
    use HasFactory;

    public const RESOURCE_NAME = 'ticket_attachment';

    protected $table = 'ticket_attachments';

    protected $fillable = [
        'ticket_message_id',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size_bytes',
        'sha256',
        'discord_attachment_id',
        'source_url',
    ];

    protected $casts = [
        'ticket_message_id' => 'integer',
        'size_bytes' => 'integer',
    ];

    public static array $validationRules = [
        'ticket_message_id' => 'required|exists:ticket_messages,id',
        'disk' => 'required|string|max:64',
        'path' => 'required|string|max:2048',
        'original_name' => 'required|string|max:191',
        'mime_type' => 'nullable|string|max:191',
        'size_bytes' => 'required|integer|min:0',
        'sha256' => 'nullable|string|max:64',
        'discord_attachment_id' => 'nullable|string|max:32',
        'source_url' => 'nullable|string|max:2048',
    ];

    public function getRouteKeyName(): string
    {
        return 'id';
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(TicketMessage::class, 'ticket_message_id');
    }
}
