<?php

namespace Pterodactyl\Http\Requests\Admin\Billing;

use Pterodactyl\Http\Requests\Admin\AdminFormRequest;

class BillingGameProfileUpdateRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'enabled' => 'required|in:0,1',
        ];
    }
}
