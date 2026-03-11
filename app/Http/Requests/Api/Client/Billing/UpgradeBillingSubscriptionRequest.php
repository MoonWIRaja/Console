<?php

namespace Pterodactyl\Http\Requests\Api\Client\Billing;

use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class UpgradeBillingSubscriptionRequest extends ClientApiRequest
{
    public function rules(): array
    {
        return [
            'cpu_cores' => 'required|integer|min:1',
            'memory_gb' => 'required|integer|min:1',
            'disk_gb' => 'required|integer|min:10',
        ];
    }
}
