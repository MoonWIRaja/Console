<?php

namespace Pterodactyl\Http\Controllers\Api\Client;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Pterodactyl\Models\Ticket;
use Pterodactyl\Services\Tickets\TicketService;
use Pterodactyl\Services\Tickets\TicketDiscordService;
use Pterodactyl\Services\Tickets\TicketSettingsService;
use Pterodactyl\Services\Tickets\TicketEligibilityService;
use Pterodactyl\Services\Tickets\TicketMessageService;
use Pterodactyl\Services\Tickets\TicketTransformerService;
use Pterodactyl\Http\Requests\Api\Client\Tickets\CreateTicketRequest;
use Pterodactyl\Http\Requests\Api\Client\Tickets\PostTicketMessageRequest;

class TicketController extends ClientApiController
{
    public function __construct(
        private TicketService $tickets,
        private TicketMessageService $messages,
        private TicketDiscordService $discord,
        private TicketSettingsService $settings,
        private TicketEligibilityService $eligibility,
        private TicketTransformerService $transformer,
    ) {
        parent::__construct();
    }

    public function index(Request $request): array
    {
        $user = $request->user();

        return [
            'data' => Ticket::query()
                ->with(['assignedAdmin', 'invoice', 'payment', 'subscription', 'order'])
                ->where('user_id', $user->id)
                ->latest('updated_at')
                ->get()
                ->map(fn (Ticket $ticket) => $this->transformer->summary($ticket, $user))
                ->all(),
        ];
    }

    public function eligibles(Request $request): array
    {
        $category = (string) $request->query('category', '');

        return [
            'data' => match ($category) {
                Ticket::CATEGORY_PAYMENT => $this->eligibility->paymentEligibles($request->user()),
                Ticket::CATEGORY_REFUND => $this->eligibility->refundEligibles($request->user()),
                default => [],
            },
        ];
    }

    public function show(Request $request, Ticket $ticket): array
    {
        $this->authorize('view', $ticket);
        $ticket = $this->tickets->markRead($ticket, $request->user());

        return [
            'data' => $this->transformer->detail($ticket, $request->user()),
        ];
    }

    public function store(CreateTicketRequest $request): array
    {
        $ticket = $this->tickets->create($request->user(), $request->validated(), [
            'source' => Ticket::SOURCE_CONSOLE,
            'attachments' => $request->file('attachments', []),
        ]);

        $this->discord->scheduleTicketSyncAfterResponse($ticket->id);

        return [
            'data' => $this->transformer->detail($ticket->fresh(['messages.attachments', 'assignedAdmin', 'invoice', 'payment', 'subscription', 'order']), $request->user()),
        ];
    }

    public function postMessage(PostTicketMessageRequest $request, Ticket $ticket): array
    {
        $this->authorize('reply', $ticket);
        $message = $this->messages->postUserMessage(
            $ticket,
            $request->user(),
            (string) $request->input('body', ''),
            $request->file('attachments', [])
        );

        $this->discord->scheduleMessageRelayAfterResponse($message->id);

        return [
            'data' => $this->transformer->message($message->fresh(['attachments'])),
        ];
    }

    public function markRead(Request $request, Ticket $ticket): array
    {
        $this->authorize('view', $ticket);

        return [
            'data' => $this->transformer->detail($this->tickets->markRead($ticket, $request->user()), $request->user()),
        ];
    }

    public function reopen(Request $request, Ticket $ticket): array
    {
        $this->authorize('reply', $ticket);

        return [
            'data' => $this->transformer->detail($this->tickets->reopen($ticket, $request->user()), $request->user()),
        ];
    }

    public function stream(Request $request, Ticket $ticket): StreamedResponse
    {
        $this->authorize('view', $ticket);

        return response()->stream(function () use ($ticket) {
            $deadline = time() + 25;
            $lastUpdated = optional($ticket->updated_at)->toIso8601String();

            echo "retry: 3000\n\n";

            while (time() < $deadline) {
                $fresh = $ticket->fresh();
                $currentUpdated = optional($fresh->updated_at)->toIso8601String();
                if ($currentUpdated !== $lastUpdated) {
                    $lastUpdated = $currentUpdated;
                    echo "event: sync\n";
                    echo 'data: ' . json_encode([
                        'ticket_id' => $fresh->id,
                        'updated_at' => $currentUpdated,
                        'status' => $fresh->status,
                    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
                    @ob_flush();
                    flush();
                }

                echo "event: heartbeat\n";
                echo 'data: {"ok":true}' . "\n\n";
                @ob_flush();
                flush();
                sleep(3);
            }
        }, Response::HTTP_OK, [
            'Cache-Control' => 'no-cache, no-transform',
            'Content-Type' => 'text/event-stream',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
