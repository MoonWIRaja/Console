<?php

namespace Pterodactyl\Http\Requests\Api\Client\Billing;

use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class StoreBillingOrderRequest extends ClientApiRequest
{
    public function rules(): array
    {
        return [
            'billing_node_config_id' => 'required|integer|exists:billing_node_configs,id',
            'billing_game_profile_id' => 'required|integer|exists:billing_game_profiles,id',
            'server_name' => 'required|string|max:191',
            'cpu_cores' => 'required|integer|min:1',
            'memory_gb' => 'required|integer|min:1',
            'disk_gb' => 'required|integer|min:10',
            'variables' => 'sometimes|array',
            'variables.*' => 'nullable|string',
        ];
    }
}
