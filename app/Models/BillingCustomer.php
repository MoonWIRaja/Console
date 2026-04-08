<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingCustomer extends Model
{
    /** @use HasFactory<\Illuminate\Database\Eloquent\Factories\Factory> */
    use HasFactory;

    protected $table = 'billing_customers';

    protected $fillable = [
        'user_id',
        'provider',
        'provider_customer_id',
        'email_snapshot',
        'name_snapshot',
        'phone_snapshot',
        'address_snapshot',
        'tax_ids_snapshot',
        'default_payment_method_type',
        'default_payment_method_brand',
        'default_payment_method_last4',
        'portal_ready_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'address_snapshot' => 'array',
        'tax_ids_snapshot' => 'array',
        'portal_ready_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
