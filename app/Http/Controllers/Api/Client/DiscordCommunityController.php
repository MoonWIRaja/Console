<?php

namespace Pterodactyl\Http\Controllers\Api\Client;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use RuntimeException;
use Throwable;
use Pterodactyl\Services\Discord\DiscordCommunityService;

class DiscordCommunityController extends ClientApiController
{
    public function __construct(private DiscordCommunityService $discordCommunity)
    {
        parent::__construct();
    }

    public function index(Request $request): array
    {
        return [
            'data' => $this->discordCommunity->getFrontendStatus($request->user()),
        ];
    }

    public function join(Request $request): JsonResponse
    {
        try {
            return new JsonResponse([
                'data' => $this->discordCommunity->join($request->user()),
            ], Response::HTTP_OK);
        } catch (RuntimeException $exception) {
            return new JsonResponse([
                'data' => [
                    'success' => false,
                    'error' => $exception->getMessage(),
                    'redirect_url' => null,
                    'member' => false,
                    'role_assigned' => false,
                ],
            ], Response::HTTP_OK);
        } catch (Throwable $exception) {
            report($exception);

            return $this->errorResponse(
                Response::HTTP_INTERNAL_SERVER_ERROR,
                'An unexpected error was encountered while processing this request, please try again.'
            );
        }
    }

    private function errorResponse(int $status, string $detail): JsonResponse
    {
        return new JsonResponse([
            'errors' => [[
                'code' => $status === Response::HTTP_UNPROCESSABLE_ENTITY ? 'ValidationException' : 'ServerError',
                'status' => (string) $status,
                'detail' => $detail,
                'meta' => [
                    'source_field' => 'discord',
                    'rule' => '',
                ],
            ]],
        ], $status);
    }
}
