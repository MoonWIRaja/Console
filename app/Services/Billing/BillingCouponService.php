<?php

namespace Pterodactyl\Services\Billing;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Pterodactyl\Models\User;
use Pterodactyl\Models\BillingCoupon;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Notifications\BillingCouponAssigned;

class BillingCouponService
{
    /**
     * Normalize a raw code into the stored format (uppercase, trimmed).
     */
    public function normalizeCode(?string $code): ?string
    {
        $code = strtoupper(trim((string) $code));

        return $code === '' ? null : $code;
    }

    /**
     * Return the coupon for a code if it exists and can currently be redeemed by the
     * given user, otherwise null. Never throws — safe to use while quoting/displaying.
     *
     * $user is optional only for call sites that predate per-user coupons (e.g. a
     * plain display check with nothing else available) - passing it is how a coupon
     * restricted to one specific user actually gets enforced.
     */
    public function findUsableByCode(?string $code, ?User $user = null): ?BillingCoupon
    {
        $code = $this->normalizeCode($code);
        if ($code === null) {
            return null;
        }

        $coupon = BillingCoupon::query()->where('code', $code)->first();
        if (!$coupon || !$coupon->isRedeemable() || !$coupon->canBeRedeemedBy($user)) {
            return null;
        }

        return $coupon;
    }

    /**
     * Resolve a coupon that must be valid, throwing a user-facing error otherwise.
     */
    public function assertRedeemable(string $code, ?User $user = null): BillingCoupon
    {
        $coupon = $this->findUsableByCode($code, $user);
        if (!$coupon) {
            throw new DisplayException('This coupon code is invalid, inactive, expired, not valid for your account, or has reached its usage limit.');
        }

        return $coupon;
    }

    /**
     * Atomically consume one redemption, re-checking the limit under a row lock so a
     * limited coupon can never be over-redeemed under concurrent checkouts.
     */
    public function redeem(BillingCoupon $coupon): void
    {
        DB::transaction(function () use ($coupon) {
            /** @var BillingCoupon $locked */
            $locked = BillingCoupon::query()->whereKey($coupon->getKey())->lockForUpdate()->firstOrFail();

            if (!$locked->is_active) {
                throw new DisplayException('This coupon is no longer active.');
            }

            if ($locked->isExpired()) {
                throw new DisplayException('This coupon has expired.');
            }

            if (!$locked->isUnlimited() && $locked->redeemed_count >= $locked->max_redemptions) {
                throw new DisplayException('This coupon has reached its usage limit.');
            }

            $locked->increment('redeemed_count');
        });

        $coupon->refresh();
    }

    /**
     * Create one personal coupon per recipient - existing users by id, plus emails
     * with no account yet - each with its own freshly generated code but the same
     * discount/expiry/limit configuration, then email each recipient theirs.
     *
     * A pending email (no account) still gets a coupon and an email today: the row
     * is created with assigned_user_id null and assigned_email set, and
     * BillingCoupon::canBeRedeemedBy() matches it against whichever account later
     * registers with that address - no follow-up admin action needed once they sign
     * up. Every row shares a batch_id purely so the admin table can group them.
     *
     * @param int[] $userIds
     * @param string[] $pendingEmails Emails with no matching account yet.
     * @return BillingCoupon[]
     */
    public function createForUsers(array $data, array $userIds, array $pendingEmails = []): array
    {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        $users = User::query()->whereIn('id', $userIds)->get()->keyBy('id');
        $pendingEmails = array_values(array_unique(array_map('strtolower', $pendingEmails)));
        $batchId = (string) Str::uuid();

        $coupons = DB::transaction(function () use ($data, $userIds, $users, $pendingEmails, $batchId) {
            $created = [];
            foreach ($userIds as $userId) {
                $user = $users->get($userId);
                if (!$user) {
                    continue;
                }

                $created[] = BillingCoupon::query()->create(array_merge($data, [
                    'code' => BillingCoupon::generateCode(),
                    'assigned_user_id' => $userId,
                    'assigned_email' => $user->email,
                    'batch_id' => $batchId,
                ]));
            }

            foreach ($pendingEmails as $email) {
                $created[] = BillingCoupon::query()->create(array_merge($data, [
                    'code' => BillingCoupon::generateCode(),
                    'assigned_user_id' => null,
                    'assigned_email' => $email,
                    'batch_id' => $batchId,
                ]));
            }

            return $created;
        });

        foreach ($coupons as $coupon) {
            if ($coupon->assigned_user_id !== null) {
                $users->get($coupon->assigned_user_id)?->notify(new BillingCouponAssigned($coupon));
            } else {
                Notification::route('mail', $coupon->assigned_email)->notify(new BillingCouponAssigned($coupon));
            }
        }

        return $coupons;
    }
}
