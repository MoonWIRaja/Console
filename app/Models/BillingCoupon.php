<?php

namespace Pterodactyl\Models;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingCoupon extends Model
{
    /** @use HasFactory<\Database\Factories\BillingCouponFactory> */
    use HasFactory;

    public const TYPE_PERCENTAGE = 'percentage';
    public const TYPE_FIXED = 'fixed';

    protected $table = 'billing_coupons';

    protected $fillable = [
        'code',
        'description',
        'discount_type',
        'discount_value',
        'max_redemptions',
        'redeemed_count',
        'is_active',
        'expires_at',
        'assigned_user_id',
        'assigned_email',
        'batch_id',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'max_redemptions' => 'integer',
        'redeemed_count' => 'integer',
        'is_active' => 'boolean',
        'expires_at' => 'datetime',
    ];

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    /**
     * A coupon with no redemption cap can be used an unlimited number of times.
     */
    public function isUnlimited(): bool
    {
        return is_null($this->max_redemptions);
    }

    public function hasRemainingRedemptions(): bool
    {
        return $this->isUnlimited() || $this->redeemed_count < $this->max_redemptions;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * A coupon created via the "assign to users" flow can only be redeemed by the
     * one person it was generated for - every other user gets the same rejection as
     * an unknown code, so this never leaks who else has a personal coupon.
     */
    public function isRestrictedToUser(): bool
    {
        return $this->assigned_user_id !== null || $this->assigned_email !== null;
    }

    /**
     * A personal coupon can be created for an email with no account yet (they were
     * emailed the code before signing up). assigned_user_id checks cover the normal
     * case; assigned_email is the fallback so the coupon still works the moment
     * that person registers with the same address, with no follow-up admin action.
     */
    public function canBeRedeemedBy(?User $user): bool
    {
        if (!$this->isRestrictedToUser()) {
            return true;
        }

        if ($user === null) {
            return false;
        }

        if ($this->assigned_user_id !== null) {
            return $user->id === $this->assigned_user_id;
        }

        return strcasecmp($user->email, (string) $this->assigned_email) === 0;
    }

    /**
     * Whether the coupon can currently be applied (active, not exhausted, not expired).
     */
    public function isRedeemable(): bool
    {
        return $this->is_active && !$this->isExpired() && $this->hasRemainingRedemptions();
    }

    /**
     * Calculate the discount this coupon applies to the given amount. The result is
     * clamped to the [0, amount] range so a coupon can never push a total negative.
     */
    public function discountFor(float $amount): float
    {
        if ($amount <= 0) {
            return 0.0;
        }

        $discount = $this->discount_type === self::TYPE_PERCENTAGE
            ? $amount * ((float) $this->discount_value / 100)
            : (float) $this->discount_value;

        return round(min(max($discount, 0.0), $amount), 2);
    }

    /**
     * Generate a unique, human-friendly coupon code.
     */
    public static function generateCode(int $length = 10): string
    {
        do {
            $code = strtoupper(Str::random($length));
        } while (static::query()->where('code', $code)->exists());

        return $code;
    }
}
