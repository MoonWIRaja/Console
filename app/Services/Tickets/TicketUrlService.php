<?php

namespace Pterodactyl\Services\Tickets;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\URL;
use Pterodactyl\Models\Ticket;
use Pterodactyl\Models\TicketAttachment;

class TicketUrlService
{
    private const ADMIN_BASE_PATH = '/admin/tickets';
    private const CLIENT_BASE_PATH = '/tickets';

    public function clientTicketUrl(Ticket $ticket): string
    {
        return rtrim((string) config('app.url', ''), '/') . self::CLIENT_BASE_PATH . '/' . $ticket->id;
    }

    public function adminTicketUrl(Ticket $ticket): string
    {
        return rtrim((string) config('app.url', ''), '/') . self::ADMIN_BASE_PATH . '/' . $ticket->id;
    }

    public function composeUrl(string $category, array $params = []): string
    {
        $query = array_filter(array_merge(['compose' => $category], $params), fn ($value) => !is_null($value) && $value !== '');

        return rtrim((string) config('app.url', ''), '/') . self::CLIENT_BASE_PATH . '?' . http_build_query($query);
    }

    public function signedAttachmentUrl(TicketAttachment $attachment, int $minutes = 60): string
    {
        return URL::temporarySignedRoute(
            'tickets.attachments.download',
            CarbonImmutable::now()->addMinutes($minutes),
            ['ticketAttachment' => $attachment->id]
        );
    }
}
