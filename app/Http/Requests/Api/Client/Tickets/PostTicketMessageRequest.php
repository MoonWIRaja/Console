<?php

namespace Pterodactyl\Http\Requests\Api\Client\Tickets;

use Illuminate\Validation\Validator;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class PostTicketMessageRequest extends ClientApiRequest
{
    public function rules(): array
    {
        $mimeTypes = implode(',', config('tickets.attachments.allowed_mime_types', []));

        return [
            'body' => 'nullable|string|max:10000',
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
            if (!$this->filled('body') && !$this->hasFile('attachments')) {
                $validator->errors()->add('body', 'A message or at least one attachment is required.');
            }
        });
    }
}
