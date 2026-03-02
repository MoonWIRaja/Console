<?php

namespace Pterodactyl\Http\Controllers\Auth;

use Illuminate\Http\JsonResponse;
use Pterodactyl\Models\User;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Events\Auth\FailedPasswordReset;
use Pterodactyl\Services\Auth\PasswordResetPinService;
use Pterodactyl\Http\Requests\Auth\RequestPasswordResetPinRequest;

class ForgotPasswordController extends Controller
{
    public function __construct(private PasswordResetPinService $passwordResetPinService)
    {
    }

    /**
     * Send a password reset verification PIN to a user email.
     */
    public function sendResetLinkEmail(RequestPasswordResetPinRequest $request): JsonResponse
    {
        $email = mb_strtolower((string) $request->input('email'));
        $user = User::query()->where('email', $email)->first();

        if ($user instanceof User) {
            $token = $this->passwordResetPinService->issueChallenge($request, $user);
            Activity::event('auth:password-reset-pin.requested')->withRequestMetadata()->subject($user)->log();
        } else {
            $token = $this->passwordResetPinService->issueAnonymousChallenge($request);
            event(new FailedPasswordReset($request->ip(), $email));
        }

        return response()->json([
            'status' => 'If that email exists in our system, a reset PIN has been sent.',
            'pin_required' => true,
            'reset_token' => $token,
        ]);
    }
}
