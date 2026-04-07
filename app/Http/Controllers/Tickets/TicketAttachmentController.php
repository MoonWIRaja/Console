<?php

namespace Pterodactyl\Http\Controllers\Tickets;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Pterodactyl\Models\TicketAttachment;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Security\SecurityQuarantineService;

class TicketAttachmentController extends Controller
{
    public function __construct(private SecurityQuarantineService $quarantine)
    {
    }

    public function download(Request $request, TicketAttachment $ticketAttachment): StreamedResponse
    {
        abort_unless($request->hasValidSignature(), 403);

        $ticketAttachment->loadMissing('message.ticket');
        $ticket = $ticketAttachment->message?->ticket;
        $user = $request->user();

        abort_unless($user && $ticket && ($user->root_admin || $ticket->user_id === $user->id), 403);
        abort_if($this->quarantine->activeForTarget($ticketAttachment), 423, 'This attachment is quarantined by Security Center.');

        return Storage::disk($ticketAttachment->disk)->download(
            $ticketAttachment->path,
            $ticketAttachment->original_name
        );
    }
}
