<?php

namespace Pterodactyl\Http\Requests\Api\Client\Billing;

use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class ToggleBillingAutoRenewRequest extends ClientApiRequest
{
    public function rules(): array
    {
        return [
            'auto_renew' => 'required|boolean',
        ];
    }
}
