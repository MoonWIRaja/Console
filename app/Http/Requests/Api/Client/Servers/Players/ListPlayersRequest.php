<?php

namespace Pterodactyl\Http\Requests\Api\Client\Servers\Players;

use Illuminate\Validation\Rule;
use Pterodactyl\Models\Permission;
use Pterodactyl\Services\Servers\Players\PlayerScope;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class ListPlayersRequest extends ClientApiRequest
{
    public function permission(): string
    {
        return Permission::ACTION_CONTROL_CONSOLE;
    }

    public function rules(): array
    {
        return [
            'scope' => ['sometimes', 'string', Rule::in(PlayerScope::all())],
            'search' => ['sometimes', 'nullable', 'string', 'max:64'],
        ];
    }
}
