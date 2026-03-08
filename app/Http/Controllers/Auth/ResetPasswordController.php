<?php

namespace Pterodactyl\Http\Controllers\Auth;

use Illuminate\Support\Str;
use Pterodactyl\Models\User;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Facades\Activity;
use Illuminate\Auth\AuthManager;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Events\Dispatcher;
use Pterodactyl\Events\User\PasswordChanged;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Http\Requests\Auth\ResetPasswordRequest;
use Pterodactyl\Contracts\Repository\UserRepositoryInterface;
use Pterodactyl\Services\Auth\PasswordResetPinService;
use Pterodactyl\Services\Security\AuthSecurityService;

class ResetPasswordController extends Controller
{
    /**
     * The URL to redirect users to after password reset.
     */
    public string $redirectTo = '/';

    protected bool $hasTwoFactor = false;

    /**
     * ResetPasswordController constructor.
     */
    public function __construct(
        private Dispatcher $dispatcher,
        private Hasher $hasher,
        private UserRepositoryInterface $userRepository,
        private PasswordResetPinService $passwordResetPinService,
        private AuthSecurityService $security,
        private AuthManager $auth,
    ) {
    }

    /**
     * Reset the given user's password using a 6-digit PIN challenge.
     *
     * @throws DisplayException
     */
    public function __invoke(ResetPasswordRequest $request): JsonResponse
    {
        $identifier = $this->security->getIdentifierFromRequest($request);
        $details = $request->session()->get('password_reset_pin_token');
        if (
            !$this->passwordResetPinService->hasValidSessionData($details)
            || empty($details['user_id'])
        ) {
            $this->security->registerFailure($request, 4, 'failed_password_reset', $identifier);
            throw new DisplayException('The reset session has expired. Please request a new PIN.');
        }

        /** @var User|null $user */
        $user = User::query()->find($details['user_id']);
        if (!$user instanceof User) {
            $this->security->registerFailure($request, 4, 'failed_password_reset', $identifier);
            throw new DisplayException('The reset code provided is invalid or has expired.');
        }

        if (!$this->passwordResetPinService->isPinValid($user, (string) $request->input('pin'))) {
            $this->security->registerFailure($request, 4, 'failed_password_reset', $identifier);
            throw new DisplayException('The reset code provided is invalid or has expired.');
        }

        $user = $this->userRepository->update($user->id, [
            'password' => $this->hasher->make((string) $request->input('password')),
            $user->getRememberTokenName() => Str::random(60),
        ]);

        $this->dispatcher->dispatch(new PasswordReset($user));
        PasswordChanged::dispatch($user);

        $this->passwordResetPinService->clearChallenge($request, $user);
        $this->security->clearRisk($request, $user->email);
        Activity::event('auth:password-reset-pin.completed')->withRequestMetadata()->subject($user)->log();

        if (!$user->use_totp) {
            $this->auth->guard()->login($user);
        }

        $this->hasTwoFactor = $user->use_totp;

        return $this->sendResetResponse();
    }

    /**
     * Send a successful password reset response back to the callee.
     */
    protected function sendResetResponse(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'redirect_to' => $this->redirectTo,
            'send_to_login' => $this->hasTwoFactor,
        ]);
    }
}
