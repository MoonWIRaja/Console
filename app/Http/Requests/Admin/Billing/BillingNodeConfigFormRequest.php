<?php

namespace Pterodactyl\Http\Requests\Admin\Billing;

use Pterodactyl\Http\Requests\Admin\AdminFormRequest;

class BillingNodeConfigFormRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'enabled' => 'required|in:0,1',
            'display_name' => 'required|string|max:191',
            'description' => 'nullable|string',
            'cpu_stock' => 'required|integer|min:0',
            'memory_stock_gb' => 'required|integer|min:0',
            'disk_stock_gb' => 'required|integer|min:0',
            'show_remaining_capacity' => 'required|in:0,1',
            'price_per_vcore' => 'required|numeric|min:0',
            'price_per_gb_ram' => 'required|numeric|min:0',
            'price_per_10gb_disk' => 'required|numeric|min:0',
            'default_allocation_limit' => 'required|integer|min:0',
            'default_database_limit' => 'required|integer|min:0',
            'default_backup_limit' => 'required|integer|min:0',
            'default_swap' => 'required|integer|min:-1',
            'default_io' => 'required|integer|between:10,1000',
            'default_oom_disabled' => 'required|in:0,1',
            'start_on_completion' => 'required|in:0,1',
        ];
    }
}
