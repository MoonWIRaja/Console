<?php

namespace Pterodactyl\Http\Controllers\Tickets;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Pterodactyl\Models\TicketAttachment;
use Pterodactyl\Http\Controllers\Controller;

class TicketAttachmentController extends Controller
{
    public function download(Request $request, TicketAttachment $ticketAttachment): StreamedResponse
    {
        abort_unless($request->hasValidSignature(), 403);

        $ticketAttachment->loadMissing('message.ticket');
        $ticket = $ticketAttachment->message?->ticket;
        $user = $request->user();

        abort_unless($user && $ticket && ($user->root_admin || $ticket->user_id === $user->id), 403);

        return Storage::disk($ticketAttachment->disk)->download(
            $ticketAttachment->path,
            $ticketAttachment->original_name
        );
    }
}
