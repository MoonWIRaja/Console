<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BillingOrder extends Model
{
    /** @use HasFactory<\Database\Factories\BillingOrderFactory> */
    use HasFactory;

    public const TYPE_NEW_SERVER = 'new_server';
    public const TYPE_RENEWAL = 'renewal';
    public const TYPE_UPGRADE = 'upgrade';
    public const TYPE_MANUAL = 'manual';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_AWAITING_PAYMENT = 'awaiting_payment';
    public const STATUS_PAID = 'paid';
    public const STATUS_QUEUED_PROVISION = 'queued_provision';
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROVISIONING = 'provisioning';
    public const STATUS_PROVISIONED = 'provisioned';
    public const STATUS_PROVISION_FAILED = 'provision_failed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REFUNDED = 'refunded';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_FAILED = 'failed';

    public const ACTIVE_RESERVATION_STATUSES = [
        self::STATUS_AWAITING_PAYMENT,
        self::STATUS_PENDING,
        self::STATUS_PAID,
        self::STATUS_QUEUED_PROVISION,
        self::STATUS_PROVISIONING,
    ];

    protected $table = 'billing_orders';

    protected $fillable = [
        'user_id',
        'billing_node_config_id',
        'billing_game_profile_id',
        'billing_invoice_id',
        'node_id',
        'egg_id',
        'server_id',
        'approved_by',
        'order_type',
        'status',
        'server_name',
        'node_name',
        'game_name',
        'cpu_cores',
        'memory_gb',
        'disk_gb',
        'price_per_vcore',
        'price_per_gb_ram',
        'price_per_10gb_disk',
        'cpu_total',
        'memory_total',
        'disk_total',
        'total',
        'docker_image',
        'startup',
        'environment',
        'billing_profile_snapshot',
        'allocation_limit',
        'database_limit',
        'backup_limit',
        'swap',
        'io',
        'oom_disabled',
        'start_on_completion',
        'order_notes',
        'admin_notes',
        'approved_at',
        'payment_verified_at',
        'provision_attempted_at',
        'provision_failure_code',
        'provision_failure_message',
        'rejected_at',
        'provisioned_at',
        'failed_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'billing_node_config_id' => 'integer',
        'billing_game_profile_id' => 'integer',
        'billing_invoice_id' => 'integer',
        'node_id' => 'integer',
        'egg_id' => 'integer',
        'server_id' => 'integer',
        'approved_by' => 'integer',
        'cpu_cores' => 'integer',
        'memory_gb' => 'integer',
        'disk_gb' => 'integer',
        'price_per_vcore' => 'decimal:2',
        'price_per_gb_ram' => 'decimal:2',
        'price_per_10gb_disk' => 'decimal:2',
        'cpu_total' => 'decimal:2',
        'memory_total' => 'decimal:2',
        'disk_total' => 'decimal:2',
        'total' => 'decimal:2',
        'environment' => 'array',
        'billing_profile_snapshot' => 'array',
        'allocation_limit' => 'integer',
        'database_limit' => 'integer',
        'backup_limit' => 'integer',
        'swap' => 'integer',
        'io' => 'integer',
        'oom_disabled' => 'boolean',
        'start_on_completion' => 'boolean',
        'approved_at' => 'datetime',
        'payment_verified_at' => 'datetime',
        'provision_attempted_at' => 'datetime',
        'rejected_at' => 'datetime',
        'provisioned_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public static array $validationRules = [
        'user_id' => 'required|exists:users,id',
        'billing_node_config_id' => 'required|exists:billing_node_configs,id',
        'node_id' => 'required|exists:nodes,id',
        'egg_id' => 'required|exists:eggs,id',
        'status' => 'required|string|max:32',
        'server_name' => 'required|string|max:191',
        'node_name' => 'required|string|max:191',
        'game_name' => 'required|string|max:191',
        'cpu_cores' => 'required|integer|min:1',
        'memory_gb' => 'required|integer|min:1',
        'disk_gb' => 'required|integer|min:10',
        'price_per_vcore' => 'required|numeric|min:0',
        'price_per_gb_ram' => 'required|numeric|min:0',
        'price_per_10gb_disk' => 'required|numeric|min:0',
        'cpu_total' => 'required|numeric|min:0',
        'memory_total' => 'required|numeric|min:0',
        'disk_total' => 'required|numeric|min:0',
        'total' => 'required|numeric|min:0',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\Pterodactyl\Models\User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\Pterodactyl\Models\BillingNodeConfig, $this>
     */
    public function nodeConfig(): BelongsTo
    {
        return $this->belongsTo(BillingNodeConfig::class, 'billing_node_config_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\Pterodactyl\Models\BillingGameProfile, $this>
     */
    public function gameProfile(): BelongsTo
    {
        return $this->belongsTo(BillingGameProfile::class, 'billing_game_profile_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(BillingInvoice::class, 'billing_invoice_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\Pterodactyl\Models\Node, $this>
     */
    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\Pterodactyl\Models\Egg, $this>
     */
    public function egg(): BelongsTo
    {
        return $this->belongsTo(Egg::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\Pterodactyl\Models\Server, $this>
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\Pterodactyl\Models\User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(BillingInvoice::class, 'billing_order_id');
    }
}
