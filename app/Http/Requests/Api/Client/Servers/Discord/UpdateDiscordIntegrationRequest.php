<?php

namespace Pterodactyl\Http\Requests\Api\Client\Servers\Discord;

use Pterodactyl\Models\Permission;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class UpdateDiscordIntegrationRequest extends ClientApiRequest
{
    public function permission(): string
    {
        return Permission::ACTION_DISCORD_MANAGE;
    }

    public function rules(): array
    {
        return [
            'enabled' => ['sometimes', 'boolean'],
            'bot_token' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'guild_id' => ['sometimes', 'nullable', 'regex:/^\d{17,20}$/'],
            'chat_channel_id' => ['sometimes', 'nullable', 'regex:/^\d{17,20}$/'],
            'console_channel_id' => ['sometimes', 'nullable', 'regex:/^\d{17,20}$/'],
            'admin_channel_id' => ['sometimes', 'nullable', 'regex:/^\d{17,20}$/'],
            'link_channel_id' => ['sometimes', 'nullable', 'regex:/^\d{17,20}$/'],
            'chat_bridge_enabled' => ['sometimes', 'boolean'],
            'console_bridge_enabled' => ['sometimes', 'boolean'],
            'linking_enabled' => ['sometimes', 'boolean'],
            'whitelist_requires_link' => ['sometimes', 'boolean'],
            'features' => ['sometimes', 'array'],
        ];
    }
}
