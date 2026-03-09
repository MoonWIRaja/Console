<?php

namespace Pterodactyl\Http\Requests\Admin\Billing;

use Pterodactyl\Http\Requests\Admin\AdminFormRequest;

class BillingOrderDecisionRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'admin_notes' => 'nullable|string',
        ];
    }
}
