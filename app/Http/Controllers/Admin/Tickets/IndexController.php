<?php

namespace Pterodactyl\Http\Controllers\Admin\Tickets;

use Illuminate\Http\Request;
use Illuminate\View\View;
use Pterodactyl\Models\Ticket;
use Pterodactyl\Http\Controllers\Controller;

class IndexController extends Controller
{
    public function index(Request $request): View
    {
        $inbox = (string) $request->query('inbox', 'open');
        $category = (string) $request->query('category', 'all');

        $tickets = Ticket::query()
            ->with(['user', 'assignedAdmin', 'invoice', 'payment', 'subscription', 'order'])
            ->when($inbox === 'open', fn ($query) => $query->whereIn('status', [
                Ticket::STATUS_WAITING_FOR_STAFF,
                Ticket::STATUS_WAITING_FOR_USER,
            ]))
            ->when($inbox === 'mine', fn ($query) => $query->where('assigned_admin_id', $request->user()->id))
            ->when($inbox === 'resolved', fn ($query) => $query->where('status', Ticket::STATUS_RESOLVED))
            ->when($inbox === 'closed', fn ($query) => $query->where('status', Ticket::STATUS_CLOSED))
            ->when($category !== 'all', fn ($query) => $query->where('category', $category))
            ->latest('updated_at')
            ->paginate(25);

        return view('admin.tickets.index', [
            'tickets' => $tickets,
            'inbox' => $inbox,
            'category' => $category,
        ]);
    }
}
