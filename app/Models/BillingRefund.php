<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingRefund extends Model
{
    /** @use HasFactory<\Database\Factories\BillingRefundFactory> */
    use HasFactory;

    public const STATUS_REQUESTED = 'requested';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected $table = 'billing_refunds';

    protected $fillable = [
        'payment_id',
        'refund_number',
        'provider_refund_id',
        'provider_charge_id',
        'provider_payment_intent_id',
        'provider_refund_status',
        'refund_scope',
        'source_revision_id',
        'amount',
        'reason',
        'status',
        'requested_by',
        'requested_at',
        'completed_at',
        'raw_response',
    ];

    protected $casts = [
        'payment_id' => 'integer',
        'requested_by' => 'integer',
        'source_revision_id' => 'integer',
        'amount' => 'decimal:2',
        'requested_at' => 'datetime',
        'completed_at' => 'datetime',
        'raw_response' => 'array',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(BillingPayment::class, 'payment_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function sourceRevision(): BelongsTo
    {
        return $this->belongsTo(BillingSubscriptionRevision::class, 'source_revision_id');
    }
}
