<?php

namespace Pterodactyl\Models\Ai;

use Pterodactyl\Models\User;
use Pterodactyl\Models\Server;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Who used Anney, where, and which actions ran. Never stores chat message text (PDPA).
 */
class AiAuditLog extends Model
{
    use Prunable;

    public const RETENTION_DAYS = 180;
    public const UPDATED_AT = null;

    protected $table = 'ai_audit_logs';
    protected $guarded = ['id'];
    protected $casts = ['detail' => 'array'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subDays(self::RETENTION_DAYS));
    }

    public static function record(string $event, array $attributes = []): void
    {
        try {
            static::query()->create(['event' => $event, 'channel' => 'panel'] + $attributes);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
