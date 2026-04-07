<?php

namespace Pterodactyl\Http\Requests\Api\Client\Servers\Startup;

use Illuminate\Validation\Rule;
use Pterodactyl\Models\Permission;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class UpdateStartupEggRequest extends ClientApiRequest
{
    public function permission(): string
    {
        return Permission::ACTION_STARTUP_UPDATE;
    }

    public function rules(): array
    {
        return [
            'nest_id' => ['required', 'integer', 'exists:nests,id'],
            'egg_id' => [
                'required',
                'integer',
                Rule::exists('eggs', 'id')->where(fn ($query) => $query->where('nest_id', (int) $this->input('nest_id'))),
            ],
            'docker_image' => 'nullable|string|max:191',
        ];
    }
}
