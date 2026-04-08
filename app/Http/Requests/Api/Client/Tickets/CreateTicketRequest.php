<?php

namespace Pterodactyl\Http\Requests\Api\Client\Tickets;

use Illuminate\Validation\Validator;
use Pterodactyl\Models\Ticket;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class CreateTicketRequest extends ClientApiRequest
{
    public function rules(): array
    {
        $mimeTypes = implode(',', config('tickets.attachments.allowed_mime_types', []));

        return [
            'category' => 'required|string|in:' . implode(',', [
                Ticket::CATEGORY_PAYMENT,
                Ticket::CATEGORY_REFUND,
                Ticket::CATEGORY_SUPPORT,
            ]),
            'subject' => 'nullable|string|max:191',
            'body' => 'nullable|string|max:10000',
            'billing_invoice_id' => 'nullable|integer|exists:billing_invoices,id',
            'billing_payment_id' => 'nullable|integer|exists:billing_payments,id',
            'billing_order_id' => 'nullable|integer|exists:billing_orders,id',
            'billing_subscription_id' => 'nullable|integer|exists:billing_subscriptions,id',
            'support_server_id' => 'nullable|integer|min:0',
            'attachments' => 'nullable|array|max:' . max((int) config('tickets.attachments.max_files_per_message', 5), 1),
            'attachments.*' => array_filter([
                'file',
                'max:' . (max((int) config('tickets.attachments.max_file_size_mb', 20), 1) * 1024),
                $mimeTypes !== '' ? 'mimetypes:' . $mimeTypes : null,
            ]),
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function (Validator $validator) {
            $category = (string) $this->input('category');
            $hasAttachments = $this->hasFile('attachments');

            if ($category === Ticket::CATEGORY_PAYMENT && !$this->filled('billing_invoice_id')) {
                $validator->errors()->add('billing_invoice_id', 'A billing invoice is required for payment tickets.');
            }

            if ($category === Ticket::CATEGORY_REFUND && !$this->filled('billing_payment_id')) {
                $validator->errors()->add('billing_payment_id', 'A billing payment is required for refund tickets.');
            }

            if ($category === Ticket::CATEGORY_SUPPORT) {
                $serverId = $this->integer('support_server_id');
                if ($serverId > 0 && !$this->user()->accessibleServers()->where('servers.id', $serverId)->exists()) {
                    $validator->errors()->add('support_server_id', 'The selected server is not available for support tickets.');
                }

                if (!$this->filled('body') && !$hasAttachments) {
                    $validator->errors()->add('body', 'A message or at least one attachment is required for support tickets.');
                }
            }
        });
    }
}
