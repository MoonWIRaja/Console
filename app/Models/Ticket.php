<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Ticket extends Model
{
    /** @use HasFactory<\Database\Factories\TicketFactory> */
    use HasFactory;

    public const RESOURCE_NAME = 'ticket';

    public const CATEGORY_PAYMENT = 'payment';
    public const CATEGORY_REFUND = 'refund';
    public const CATEGORY_SUPPORT = 'support';

    public const SOURCE_CONSOLE = 'console';
    public const SOURCE_DISCORD = 'discord';
    public const SOURCE_CHECKOUT = 'checkout';
    public const SOURCE_AUTOMATION = 'automation';

    public const STATUS_WAITING_FOR_USER = 'waiting_for_user';
    public const STATUS_WAITING_FOR_STAFF = 'waiting_for_staff';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_CLOSED = 'closed';

    public const DISCORD_SYNC_PENDING = 'pending';
    public const DISCORD_SYNC_SYNCED = 'synced';
    public const DISCORD_SYNC_FAILED = 'failed';
    public const DISCORD_SYNC_SKIPPED = 'skipped';

    protected $table = 'tickets';

    protected $fillable = [
        'ticket_number',
        'user_id',
        'category',
        'source',
        'status',
        'subject',
        'assigned_admin_id',
        'billing_order_id',
        'billing_invoice_id',
        'billing_payment_id',
        'billing_subscription_id',
        'requester_discord_user_id',
        'requester_discord_name',
        'requester_discord_avatar',
        'discord_thread_id',
        'discord_parent_channel_id',
        'discord_sync_status',
        'discord_last_synced_at',
        'discord_last_error',
        'last_user_message_at',
        'last_admin_message_at',
        'user_last_read_at',
        'staff_last_read_at',
        'resolved_at',
        'closed_at',
        'meta',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'assigned_admin_id' => 'integer',
        'billing_order_id' => 'integer',
        'billing_invoice_id' => 'integer',
        'billing_payment_id' => 'integer',
        'billing_subscription_id' => 'integer',
        'discord_last_synced_at' => 'datetime',
        'last_user_message_at' => 'datetime',
        'last_admin_message_at' => 'datetime',
        'user_last_read_at' => 'datetime',
        'staff_last_read_at' => 'datetime',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
        'meta' => 'array',
    ];

    public static array $validationRules = [
        'ticket_number' => 'required|string|max:32|unique:tickets,ticket_number',
        'user_id' => 'required|exists:users,id',
        'category' => 'required|string|max:32',
        'source' => 'required|string|max:32',
        'status' => 'required|string|max:32',
        'subject' => 'required|string|max:191',
        'requester_discord_user_id' => 'nullable|string|max:32',
        'requester_discord_name' => 'nullable|string|max:191',
        'requester_discord_avatar' => 'nullable|string|max:2048',
        'discord_thread_id' => 'nullable|string|max:32',
        'discord_parent_channel_id' => 'nullable|string|max:32',
        'discord_sync_status' => 'nullable|string|max:32',
    ];

    public function getRouteKeyName(): string
    {
        return 'id';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_admin_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(BillingOrder::class, 'billing_order_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(BillingInvoice::class, 'billing_invoice_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(BillingPayment::class, 'billing_payment_id');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(BillingSubscription::class, 'billing_subscription_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [self::STATUS_WAITING_FOR_STAFF, self::STATUS_WAITING_FOR_USER], true);
    }
}
