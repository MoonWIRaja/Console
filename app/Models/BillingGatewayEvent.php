<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class BillingGatewayEvent extends Model
{
    /** @use HasFactory<\Database\Factories\BillingGatewayEventFactory> */
    use HasFactory;

    public const STATUS_RECEIVED = 'received';
    public const STATUS_PROCESSED = 'processed';
    public const STATUS_FAILED = 'failed';

    protected $table = 'billing_gateway_events';

    protected $fillable = [
        'provider',
        'event_type',
        'provider_event_id',
        'provider_transaction_id',
        'dedupe_key',
        'status',
        'payload',
        'processed_at',
        'processing_error',
    ];

    protected $casts = [
        'payload' => 'array',
        'processed_at' => 'datetime',
    ];
}
