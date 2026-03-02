<?php

namespace Pterodactyl\Services\Auth;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Pterodactyl\Models\User;
use Illuminate\Support\Str;
use Illuminate\Contracts\Hashing\Hasher;
use Pterodactyl\Notifications\EmailVerificationPin;

class EmailVerificationService
{
    private const PIN_EXPIRATION_MINUTES = 10;

    public function __construct(private Hasher $hasher)
    {
    }

    /**
     * Issues a fresh 6-digit verification code to the user and stores a verification session token.
     */
    public function issueChallenge(Request $request, User $user): string
    {
        $pin = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = CarbonImmutable::now()->addMinutes(self::PIN_EXPIRATION_MINUTES);

        $user->forceFill([
            'email_verification_pin' => $this->hasher->make($pin),
            'email_verification_expires_at' => $expiresAt,
        ])->save();

        $user->notify(new EmailVerificationPin($pin));

        $token = Str::random(64);
        $request->session()->put('email_verification_token', [
            'user_id' => $user->id,
            'token_value' => $token,
            'expires_at' => $expiresAt,
        ]);

        return $token;
    }

    /**
     * Determines if the session token structure and expiry are valid.
     */
    public function hasValidSessionData(?array $data): bool
    {
        if (is_null($data) || !is_array($data)) {
            return false;
        }

        if (!array_key_exists('user_id', $data) || !array_key_exists('token_value', $data) || !array_key_exists('expires_at', $data)) {
            return false;
        }

        if (!$data['expires_at'] instanceof CarbonInterface) {
            return false;
        }

        return !$data['expires_at']->isBefore(CarbonImmutable::now());
    }

    /**
     * Determines if the provided pin is valid and not expired for this user.
     */
    public function isPinValid(User $user, string $pin): bool
    {
        if ($user->is_email_verified || empty($user->email_verification_pin) || is_null($user->email_verification_expires_at)) {
            return false;
        }

        if ($user->email_verification_expires_at->isBefore(CarbonImmutable::now())) {
            return false;
        }

        return $this->hasher->check($pin, $user->email_verification_pin);
    }

    /**
     * Marks the user as verified and clears temporary verification fields.
     */
    public function markVerified(User $user): void
    {
        $user->forceFill([
            'is_email_verified' => true,
            'email_verification_pin' => null,
            'email_verification_expires_at' => null,
        ])->save();
    }
}

