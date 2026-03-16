<?php

namespace Pterodactyl\Http\Controllers\Tickets;

use RuntimeException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Tickets\TicketDiscordInteractionService;

class DiscordInteractionController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $this->verifiedPayload($request);
        if ((int) ($payload['type'] ?? 0) === 1) {
            return new JsonResponse(['type' => 1], Response::HTTP_OK);
        }

        return new JsonResponse(
            app(TicketDiscordInteractionService::class)->handle($payload),
            Response::HTTP_OK
        );
    }

    private function verifiedPayload(Request $request): array
    {
        $signature = trim((string) $request->header('X-Signature-Ed25519', ''));
        $timestamp = trim((string) $request->header('X-Signature-Timestamp', ''));
        $publicKey = trim((string) config('services.discord.application_public_key', ''));
        $body = $request->getContent();

        $decodedSignature = ctype_xdigit($signature) ? hex2bin($signature) : false;
        $decodedPublicKey = ctype_xdigit($publicKey) ? hex2bin($publicKey) : false;

        if (
            $signature === ''
            || $timestamp === ''
            || $publicKey === ''
            || !$decodedSignature
            || !$decodedPublicKey
            || !function_exists('sodium_crypto_sign_verify_detached')
        ) {
            abort(Response::HTTP_UNAUTHORIZED, 'Discord interaction verification is not available.');
        }

        $verified = sodium_crypto_sign_verify_detached(
            $decodedSignature,
            $timestamp . $body,
            $decodedPublicKey
        );

        abort_unless($verified, Response::HTTP_UNAUTHORIZED, 'Discord interaction signature verification failed.');

        if ($body === '') {
            return [];
        }

        $payload = json_decode($body, true);
        if (!is_array($payload)) {
            throw new RuntimeException('Discord sent an invalid interaction payload.');
        }

        return $payload;
    }
}
