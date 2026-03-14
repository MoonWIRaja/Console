<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillingPayment extends Model
{
    /** @use HasFactory<\Database\Factories\BillingPaymentFactory> */
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

    protected $table = 'billing_payments';

    protected $fillable = [
        'invoice_id',
        'provider',
        'payment_number',
        'provider_transaction_id',
        'provider_payment_intent_id',
        'provider_charge_id',
        'provider_order_id',
        'provider_payment_method',
        'payment_method_type',
        'payment_method_brand',
        'payment_method_last4',
        'provider_status',
        'amount',
        'currency',
        'status',
        'paid_at',
        'raw_gateway_response',
    ];

    protected $casts = [
        'invoice_id' => 'integer',
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'raw_gateway_response' => 'array',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(BillingInvoice::class, 'invoice_id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(BillingPaymentAttempt::class, 'payment_id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(BillingRefund::class, 'payment_id');
    }
}
