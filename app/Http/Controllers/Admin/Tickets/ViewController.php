<?php

namespace Pterodactyl\Http\Controllers\Admin\Tickets;

use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Models\Ticket;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Tickets\TicketService;
use Pterodactyl\Services\Tickets\TicketDiscordService;
use Pterodactyl\Services\Tickets\TicketMessageService;
use Pterodactyl\Http\Requests\Admin\Tickets\UpdateTicketRequest;
use Pterodactyl\Http\Requests\Admin\Tickets\PostTicketMessageRequest;

class ViewController extends Controller
{
    public function __construct(
        private AlertsMessageBag $alert,
        private TicketService $tickets,
        private TicketMessageService $messages,
        private TicketDiscordService $discord,
    ) {
    }

    public function view(Ticket $ticket): View
    {
        return view('admin.tickets.view', [
            'ticket' => $ticket->load(['user', 'assignedAdmin', 'invoice.order', 'invoice.subscription', 'payment.invoice', 'messages.attachments']),
        ]);
    }

    public function postMessage(PostTicketMessageRequest $request, Ticket $ticket): RedirectResponse
    {
        $message = $this->messages->postAdminMessage(
            $ticket,
            $request->user(),
            (string) $request->input('body', ''),
            $request->file('attachments', [])
        );

        try {
            $this->discord->relayMessage($message->fresh(['ticket', 'attachments', 'author']));
        } catch (\Throwable $exception) {
            report($exception);
            $this->alert->warning('Message was saved in the panel, but Discord relay failed.')->flash();
        }

        $this->alert->success('Reply posted.')->flash();

        return redirect()->route('admin.tickets.view', $ticket->id);
    }

    public function update(UpdateTicketRequest $request, Ticket $ticket): RedirectResponse
    {
        $ticket = $this->tickets->updateStatus(
            $ticket,
            (string) $request->input('status'),
            $request->filled('assigned_admin_id') ? (int) $request->input('assigned_admin_id') : null
        );

        if ($ticket->status === Ticket::STATUS_CLOSED) {
            try {
                $this->discord->closeTicketThread($ticket, 'Ticket closed from admin panel');
            } catch (\Throwable $exception) {
                report($exception);
                $this->alert->warning('Ticket was closed in the panel, but the Discord thread could not be deleted automatically.')->flash();
            }
        }

        $this->alert->success('Ticket updated.')->flash();

        return redirect()->route('admin.tickets.view', $ticket->id);
    }

    public function reopen(Ticket $ticket): RedirectResponse
    {
        $this->tickets->reopen($ticket, request()->user());
        $this->alert->success('Ticket reopened.')->flash();

        return redirect()->route('admin.tickets.view', $ticket->id);
    }
}
