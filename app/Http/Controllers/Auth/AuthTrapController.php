<?php

namespace Pterodactyl\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Security\AuthSecurityService;

class AuthTrapController extends Controller
{
    public function __construct(private AuthSecurityService $security)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $identifier = $this->security->getIdentifierFromRequest($request);
        $score = $this->security->registerFailure($request, 10, 'honeypot_triggered', $identifier);

        usleep(random_int(500000, 1200000));

        return response()->json([
            'errors' => [
                [
                    'code' => 'InvalidRequest',
                    'status' => (string) Response::HTTP_NOT_FOUND,
                    'detail' => 'The requested resource could not be found on the server.',
                ],
            ],
            'challenge_required' => $score >= (int) config('security.risk.challenge_threshold', 10),
            'next_action' => 'retry',
        ], Response::HTTP_NOT_FOUND);
    }
}
