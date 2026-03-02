<?php

namespace Pterodactyl\Services\Auth;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Pterodactyl\Models\User;
use Illuminate\Contracts\Hashing\Hasher;
use Pterodactyl\Notifications\PasswordResetPin;

class PasswordResetPinService
{
    private const PIN_EXPIRATION_MINUTES = 10;

    public function __construct(private Hasher $hasher)
    {
    }

    /**
     * Issue a reset PIN for a user and persist a challenge token to session.
     */
    public function issueChallenge(Request $request, User $user): string
    {
        $pin = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = CarbonImmutable::now()->addMinutes(self::PIN_EXPIRATION_MINUTES);

        $user->forceFill([
            'password_reset_pin' => $this->hasher->make($pin),
            'password_reset_expires_at' => $expiresAt,
        ])->save();

        $user->notify(new PasswordResetPin($pin));

        $token = Str::random(64);
        $request->session()->put('password_reset_pin_token', [
            'user_id' => $user->id,
            'token_value' => $token,
            'expires_at' => $expiresAt,
        ]);

        return $token;
    }

    /**
     * Issue a fake challenge token when the account does not exist.
     */
    public function issueAnonymousChallenge(Request $request): string
    {
        $token = Str::random(64);
        $request->session()->put('password_reset_pin_token', [
            'user_id' => null,
            'token_value' => $token,
            'expires_at' => CarbonImmutable::now()->addMinutes(self::PIN_EXPIRATION_MINUTES),
        ]);

        return $token;
    }

    /**
     * Validate challenge token shape and expiry.
     */
    public function hasValidSessionData(?array $data): bool
    {
        if (is_null($data) || !is_array($data)) {
            return false;
        }

        if (!array_key_exists('token_value', $data) || !array_key_exists('expires_at', $data)) {
            return false;
        }

        if (!$data['expires_at'] instanceof CarbonInterface) {
            return false;
        }

        return !$data['expires_at']->isBefore(CarbonImmutable::now());
    }

    /**
     * Validate a user PIN and expiry.
     */
    public function isPinValid(User $user, string $pin): bool
    {
        if (empty($user->password_reset_pin) || is_null($user->password_reset_expires_at)) {
            return false;
        }

        if ($user->password_reset_expires_at->isBefore(CarbonImmutable::now())) {
            return false;
        }

        return $this->hasher->check($pin, $user->password_reset_pin);
    }

    /**
     * Clear password reset challenge state from user and session.
     */
    public function clearChallenge(Request $request, User $user): void
    {
        $request->session()->remove('password_reset_pin_token');

        $user->forceFill([
            'password_reset_pin' => null,
            'password_reset_expires_at' => null,
        ])->save();
    }
}
