<?php

namespace Pterodactyl\Http\Controllers\Api\Client;

use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class BillingController extends ClientApiController
{
    public function catalog(): array
    {
        return [
            'data' => [],
            'meta' => [
                'coming_soon' => true,
            ],
        ];
    }

    public function orders(Request $request): array
    {
        return [
            'data' => [],
            'meta' => [
                'coming_soon' => true,
            ],
        ];
    }

    public function store(): JsonResponse
    {
        throw ValidationException::withMessages([
            'billing' => 'Billing is coming soon.',
        ])->status(Response::HTTP_CONFLICT);
    }
}
