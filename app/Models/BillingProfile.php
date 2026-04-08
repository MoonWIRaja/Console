<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillingProfile extends Model
{
    /** @use HasFactory<\Database\Factories\BillingProfileFactory> */
    use HasFactory;

    protected $table = 'billing_profiles';

    protected $fillable = [
        'user_id',
        'legal_name',
        'company_name',
        'email',
        'phone',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'postcode',
        'country_code',
        'tax_id',
        'is_business',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'is_business' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(BillingInvoice::class);
    }
}
