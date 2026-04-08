<?php

namespace Pterodactyl\Http\Requests\Admin\Security;

use Pterodactyl\Http\Requests\Admin\AdminFormRequest;

class StoreSecurityAgentRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'nullable|string|max:191|required_without:node_id',
            'node_id' => 'nullable|integer|exists:nodes,id|unique:security_agents,node_id',
            'capabilities' => 'nullable|string|max:4096',
        ];
    }

    public function capabilities(): array
    {
        return array_values(array_filter(array_map(
            'trim',
            explode(',', (string) $this->input('capabilities', ''))
        )));
    }
}
