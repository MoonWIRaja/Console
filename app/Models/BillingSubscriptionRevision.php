<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingSubscriptionRevision extends Model
{
    /** @use HasFactory<\Illuminate\Database\Eloquent\Factories\Factory> */
    use HasFactory;

    public const TYPE_NEW_SERVER = 'new_server';
    public const TYPE_UPGRADE = 'upgrade';
    public const TYPE_REFUND_ROLLBACK = 'refund_rollback';
    public const TYPE_ADMIN_ADJUSTMENT = 'admin_adjustment';

    protected $table = 'billing_subscription_revisions';

    protected $fillable = [
        'subscription_id',
        'source_invoice_id',
        'source_order_id',
        'previous_revision_id',
        'revision_type',
        'cpu_cores',
        'memory_gb',
        'disk_gb',
        'recurring_total',
        'stripe_price_id',
        'stripe_price_snapshot',
        'applied_at',
    ];

    protected $casts = [
        'subscription_id' => 'integer',
        'source_invoice_id' => 'integer',
        'source_order_id' => 'integer',
        'previous_revision_id' => 'integer',
        'cpu_cores' => 'integer',
        'memory_gb' => 'integer',
        'disk_gb' => 'integer',
        'recurring_total' => 'decimal:2',
        'stripe_price_snapshot' => 'array',
        'applied_at' => 'datetime',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(BillingSubscription::class, 'subscription_id');
    }

    public function sourceInvoice(): BelongsTo
    {
        return $this->belongsTo(BillingInvoice::class, 'source_invoice_id');
    }

    public function sourceOrder(): BelongsTo
    {
        return $this->belongsTo(BillingOrder::class, 'source_order_id');
    }

    public function previousRevision(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_revision_id');
    }
}
