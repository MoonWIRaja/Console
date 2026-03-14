<?php

namespace Pterodactyl\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BillingSubscription extends Model
{
    /** @use HasFactory<\Database\Factories\BillingSubscriptionFactory> */
    use HasFactory;

    public const STATUS_PENDING_ACTIVATION = 'pending_activation';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAST_DUE = 'past_due';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_DELETED = 'deleted';
    public const RENEWAL_WINDOW_DAYS = 2;

    public const RESOURCE_RESERVATION_STATUSES = [
        self::STATUS_PENDING_ACTIVATION,
        self::STATUS_ACTIVE,
        self::STATUS_PAST_DUE,
        self::STATUS_SUSPENDED,
    ];

    protected $table = 'billing_subscriptions';

    protected $fillable = [
        'user_id',
        'server_id',
        'billing_node_config_id',
        'billing_game_profile_id',
        'billing_order_id',
        'last_paid_invoice_id',
        'status',
        'auto_renew',
        'gateway_provider',
        'gateway_customer_reference',
        'gateway_token_reference',
        'provider_subscription_id',
        'provider_subscription_item_id',
        'provider_price_id',
        'provider_status',
        'provider_current_period_start',
        'provider_current_period_end',
        'provider_cancel_at',
        'migration_source',
        'migration_state',
        'server_name',
        'node_name',
        'game_name',
        'cpu_cores',
        'memory_gb',
        'disk_gb',
        'price_per_vcore',
        'price_per_gb_ram',
        'price_per_10gb_disk',
        'recurring_total',
        'renewal_period_months',
        'renews_at',
        'next_invoice_at',
        'grace_suspend_at',
        'grace_delete_at',
        'failed_payment_count',
        'renewal_reminder_sent_at',
        'renewed_at',
        'upgraded_at',
        'suspended_at',
        'deletion_scheduled_at',
        'deleted_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'server_id' => 'integer',
        'billing_node_config_id' => 'integer',
        'billing_game_profile_id' => 'integer',
        'billing_order_id' => 'integer',
        'last_paid_invoice_id' => 'integer',
        'auto_renew' => 'boolean',
        'provider_current_period_start' => 'datetime',
        'provider_current_period_end' => 'datetime',
        'provider_cancel_at' => 'datetime',
        'cpu_cores' => 'integer',
        'memory_gb' => 'integer',
        'disk_gb' => 'integer',
        'price_per_vcore' => 'decimal:2',
        'price_per_gb_ram' => 'decimal:2',
        'price_per_10gb_disk' => 'decimal:2',
        'recurring_total' => 'decimal:2',
        'renewal_period_months' => 'integer',
        'renews_at' => 'datetime',
        'next_invoice_at' => 'datetime',
        'grace_suspend_at' => 'datetime',
        'grace_delete_at' => 'datetime',
        'failed_payment_count' => 'integer',
        'renewal_reminder_sent_at' => 'datetime',
        'renewed_at' => 'datetime',
        'upgraded_at' => 'datetime',
        'suspended_at' => 'datetime',
        'deletion_scheduled_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'id';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function nodeConfig(): BelongsTo
    {
        return $this->belongsTo(BillingNodeConfig::class, 'billing_node_config_id');
    }

    public function gameProfile(): BelongsTo
    {
        return $this->belongsTo(BillingGameProfile::class, 'billing_game_profile_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(BillingOrder::class, 'billing_order_id');
    }

    public function lastPaidInvoice(): BelongsTo
    {
        return $this->belongsTo(BillingInvoice::class, 'last_paid_invoice_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(BillingInvoice::class, 'subscription_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(BillingSubscriptionRevision::class, 'subscription_id');
    }

    public function hasAttachedServer(): bool
    {
        return !is_null($this->server_id);
    }

    public function renewAvailableAt(): ?CarbonImmutable
    {
        if (is_null($this->renews_at)) {
            return null;
        }

        return CarbonImmutable::instance($this->renews_at)->subDays(self::RENEWAL_WINDOW_DAYS);
    }

    public function isRenewWindowOpen(?CarbonImmutable $now = null): bool
    {
        if (in_array($this->status, [self::STATUS_DELETED, self::STATUS_CANCELLED], true) || !$this->hasAttachedServer()) {
            return false;
        }

        if ($this->status === self::STATUS_SUSPENDED) {
            return true;
        }

        if ($this->status !== self::STATUS_ACTIVE) {
            return false;
        }

        $availableAt = $this->renewAvailableAt();
        if (is_null($availableAt)) {
            return false;
        }

        $now ??= CarbonImmutable::now();

        return $availableAt->lessThanOrEqualTo($now);
    }

    public function isInGraceWindow(?CarbonImmutable $now = null): bool
    {
        $now ??= CarbonImmutable::now();

        return !is_null($this->grace_suspend_at) && CarbonImmutable::instance($this->grace_suspend_at)->lessThanOrEqualTo($now);
    }
}
