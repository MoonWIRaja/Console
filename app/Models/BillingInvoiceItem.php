<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingInvoiceItem extends Model
{
    /** @use HasFactory<\Database\Factories\BillingInvoiceItemFactory> */
    use HasFactory;

    public const TYPE_BASE_PLAN = 'base_plan';
    public const TYPE_UPGRADE_PRORATION = 'upgrade_proration';
    public const TYPE_TAX = 'tax';
    public const TYPE_MANUAL_ADJUSTMENT = 'manual_adjustment';

    protected $table = 'billing_invoice_items';

    protected $fillable = [
        'invoice_id',
        'type',
        'description',
        'quantity',
        'unit_amount',
        'line_subtotal',
        'meta',
    ];

    protected $casts = [
        'invoice_id' => 'integer',
        'quantity' => 'integer',
        'unit_amount' => 'decimal:2',
        'line_subtotal' => 'decimal:2',
        'meta' => 'array',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(BillingInvoice::class, 'invoice_id');
    }
}
