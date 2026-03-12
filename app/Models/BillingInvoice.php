<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillingInvoice extends Model
{
    /** @use HasFactory<\Database\Factories\BillingInvoiceFactory> */
    use HasFactory;

    public const TYPE_NEW_SERVER = 'new_server';
    public const TYPE_RENEWAL = 'renewal';
    public const TYPE_UPGRADE = 'upgrade';
    public const TYPE_MANUAL = 'manual';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_OPEN = 'open';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_PAID = 'paid';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_VOID = 'void';
    public const STATUS_FAILED = 'failed';
    public const STATUS_REFUNDED = 'refunded';
    public const STATUS_PARTIALLY_REFUNDED = 'partially_refunded';

    protected $table = 'billing_invoices';

    protected $fillable = [
        'invoice_number',
        'user_id',
        'billing_profile_id',
        'billing_order_id',
        'subscription_id',
        'type',
        'currency',
        'subtotal',
        'tax_total',
        'grand_total',
        'status',
        'issued_at',
        'due_at',
        'paid_at',
        'voided_at',
        'billing_profile_snapshot',
        'reminder_state',
        'notes',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'billing_profile_id' => 'integer',
        'billing_order_id' => 'integer',
        'subscription_id' => 'integer',
        'subtotal' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'issued_at' => 'datetime',
        'due_at' => 'datetime',
        'paid_at' => 'datetime',
        'voided_at' => 'datetime',
        'billing_profile_snapshot' => 'array',
        'reminder_state' => 'array',
    ];

    public function getRouteKeyName(): string
    {
        return 'id';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function billingProfile(): BelongsTo
    {
        return $this->belongsTo(BillingProfile::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(BillingOrder::class, 'billing_order_id');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(BillingSubscription::class, 'subscription_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(BillingInvoiceItem::class, 'invoice_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(BillingPayment::class, 'invoice_id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(BillingPaymentAttempt::class, 'invoice_id');
    }
}
