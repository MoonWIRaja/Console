<?php

namespace Pterodactyl\Http\Requests\Api\Client\Billing;

use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class StoreBillingProfileRequest extends ClientApiRequest
{
    public function rules(): array
    {
        return [
            'legal_name' => 'required|string|max:191',
            'company_name' => 'nullable|string|max:191',
            'email' => 'required|email|max:191',
            'phone' => 'nullable|string|max:32',
            'address_line_1' => 'nullable|string|max:191',
            'address_line_2' => 'nullable|string|max:191',
            'city' => 'nullable|string|max:191',
            'state' => 'nullable|string|max:191',
            'postcode' => 'nullable|string|max:32',
            'country_code' => 'required|string|size:2',
            'tax_id' => 'nullable|string|max:191',
            'is_business' => 'boolean',
        ];
    }
}
