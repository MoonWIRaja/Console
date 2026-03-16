<?php

return [
    'enabled' => env('TICKETS_ENABLED', false),
    'auto_create_on_manual_checkout' => env('TICKETS_AUTO_CREATE_ON_MANUAL_CHECKOUT', true),
    'resolve_on_paid' => env('TICKETS_RESOLVE_ON_PAID', true),
    'discord' => [
        'launcher_channel_id' => env('TICKETS_DISCORD_LAUNCHER_CHANNEL_ID'),
        'active_parent_channel_id' => env('TICKETS_DISCORD_ACTIVE_PARENT_CHANNEL_ID'),
        'log_channel_id' => env('TICKETS_DISCORD_LOG_CHANNEL_ID'),
        'staff_role_ids' => array_values(array_filter(array_map('trim', explode(',', (string) env('TICKETS_DISCORD_STAFF_ROLE_IDS', ''))))),
        'launcher_message_id' => env('TICKETS_DISCORD_LAUNCHER_MESSAGE_ID'),
        'relay_webhook_id' => env('TICKETS_DISCORD_RELAY_WEBHOOK_ID'),
        'relay_webhook_token' => env('TICKETS_DISCORD_RELAY_WEBHOOK_TOKEN'),
    ],
    'bridge' => [
        'shared_secret' => env('TICKETS_BRIDGE_SHARED_SECRET'),
        'last_heartbeat_at' => null,
        'last_heartbeat_meta' => [],
    ],
    'attachments' => [
        'disk' => env('TICKETS_ATTACHMENTS_DISK', 'local'),
        'max_files_per_message' => (int) env('TICKETS_ATTACHMENTS_MAX_FILES_PER_MESSAGE', 5),
        'max_file_size_mb' => (int) env('TICKETS_ATTACHMENTS_MAX_FILE_SIZE_MB', 20),
        'allowed_mime_types' => [
            'image/jpeg',
            'image/png',
            'image/webp',
            'image/gif',
            'application/pdf',
            'text/plain',
            'text/csv',
            'application/json',
            'application/zip',
            'application/x-zip-compressed',
        ],
    ],
];
