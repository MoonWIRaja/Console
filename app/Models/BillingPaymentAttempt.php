<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingPaymentAttempt extends Model
{
    /** @use HasFactory<\Database\Factories\BillingPaymentAttemptFactory> */
    use HasFactory;

    public const STATUS_INITIATED = 'initiated';
    public const STATUS_REDIRECTED = 'redirected';
    public const STATUS_CALLBACK_RECEIVED = 'callback_received';
    public const STATUS_VERIFIED_PAID = 'verified_paid';
    public const STATUS_VERIFIED_FAILED = 'verified_failed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REFUND_PENDING = 'refund_pending';
    public const STATUS_REFUNDED = 'refunded';
    public const STATUS_REFUND_FAILED = 'refund_failed';

    protected $table = 'billing_payment_attempts';

    protected $fillable = [
        'invoice_id',
        'payment_id',
        'provider',
        'attempt_number',
        'status',
        'checkout_reference',
        'redirected_at',
        'callback_received_at',
        'verified_at',
        'failure_reason',
        'raw_request_payload',
        'raw_callback_payload',
    ];

    protected $casts = [
        'invoice_id' => 'integer',
        'payment_id' => 'integer',
        'attempt_number' => 'integer',
        'redirected_at' => 'datetime',
        'callback_received_at' => 'datetime',
        'verified_at' => 'datetime',
        'raw_request_payload' => 'array',
        'raw_callback_payload' => 'array',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(BillingInvoice::class, 'invoice_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(BillingPayment::class, 'payment_id');
    }
}
