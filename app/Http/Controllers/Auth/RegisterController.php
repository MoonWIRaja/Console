<?php

namespace Pterodactyl\Http\Controllers\Auth;

use Ramsey\Uuid\Uuid;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Models\User;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Exceptions\DisplayException;
use Illuminate\Contracts\Hashing\Hasher;
use Pterodactyl\Services\Auth\EmailVerificationService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Pterodactyl\Http\Requests\Auth\RegisterRequest;
use Pterodactyl\Http\Requests\Auth\VerifyEmailPinRequest;
use Pterodactyl\Services\Security\AuthSecurityService;

class RegisterController extends AbstractLoginController
{
    private const TOKEN_EXPIRED_MESSAGE = 'The verification session has expired, please login and request a new code.';

    public function __construct(
        private Hasher $hasher,
        private EmailVerificationService $emailVerificationService,
        private AuthSecurityService $security,
    ) {
        parent::__construct();
    }

    /**
     * Registers a new account and sends a 6-digit pin code for email verification.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = new User();
        $user->forceFill([
            'uuid' => Uuid::uuid4()->toString(),
            'email' => $request->input('email'),
            'username' => $request->input('username'),
            'name_first' => $request->input('first_name'),
            'name_last' => $request->input('last_name'),
            'password' => $this->hasher->make($request->input('password')),
            'root_admin' => false,
            'use_totp' => false,
            'is_email_verified' => false,
            'gravatar' => true,
        ])->save();

        $token = $this->emailVerificationService->issueChallenge($request, $user);

        Activity::event('auth:signup')->withRequestMetadata()->subject($user)->log();

        return new JsonResponse([
            'data' => [
                'complete' => false,
                'email_verification_required' => true,
                'verification_token' => $token,
            ],
        ]);
    }

    /**
     * Verifies the 6-digit pin and activates the account.
     *
     * @throws \Pterodactyl\Exceptions\DisplayException
     */
    public function verify(VerifyEmailPinRequest $request): JsonResponse
    {
        $identifier = $this->security->getIdentifierFromRequest($request);

        $details = $request->session()->get('email_verification_token');
        if (!$this->emailVerificationService->hasValidSessionData($details)) {
            $this->security->registerFailure($request, 4, 'failed_signup_verify', $identifier);
            throw new DisplayException(self::TOKEN_EXPIRED_MESSAGE);
        }

        if (!hash_equals($request->input('verification_token') ?? '', $details['token_value'])) {
            $this->security->registerFailure($request, 4, 'failed_signup_verify', $identifier);
            throw new DisplayException(self::TOKEN_EXPIRED_MESSAGE);
        }

        try {
            /** @var User $user */
            $user = User::query()->findOrFail($details['user_id']);
        } catch (ModelNotFoundException) {
            $this->security->registerFailure($request, 4, 'failed_signup_verify', $identifier);
            throw new DisplayException(self::TOKEN_EXPIRED_MESSAGE);
        }

        if ($user->is_email_verified) {
            return $this->sendLoginResponse($user, $request);
        }

        if (!$this->emailVerificationService->isPinValid($user, (string) $request->input('pin'))) {
            $this->security->registerFailure($request, 4, 'failed_signup_verify', $identifier);
            throw new DisplayException('The verification code provided is invalid or has expired.');
        }

        $this->emailVerificationService->markVerified($user);

        Activity::event('auth:email-verified')->withRequestMetadata()->subject($user)->log();

        return $this->sendLoginResponse($user, $request);
    }
}
