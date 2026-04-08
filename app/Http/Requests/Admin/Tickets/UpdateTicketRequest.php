<?php

namespace Pterodactyl\Http\Requests\Admin\Tickets;

use Pterodactyl\Models\Ticket;
use Pterodactyl\Http\Requests\Admin\AdminFormRequest;

class UpdateTicketRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'status' => 'required|string|in:' . implode(',', [
                Ticket::STATUS_WAITING_FOR_USER,
                Ticket::STATUS_WAITING_FOR_STAFF,
                Ticket::STATUS_RESOLVED,
                Ticket::STATUS_CLOSED,
            ]),
            'assigned_admin_id' => 'nullable|integer|exists:users,id',
        ];
    }
}
