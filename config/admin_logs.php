<?php

return [
    'new_account' => [
        'discord_channel_id' => env('ADMIN_LOGS_NEW_ACCOUNT_DISCORD_CHANNEL_ID'),
    ],
    'payment' => [
        'discord_channel_id' => env('ADMIN_LOGS_PAYMENT_DISCORD_CHANNEL_ID'),
    ],
    'security' => [
        'discord_channel_id' => env('ADMIN_LOGS_SECURITY_DISCORD_CHANNEL_ID'),
    ],
    'login' => [
        'discord_channel_id' => env('ADMIN_LOGS_LOGIN_DISCORD_CHANNEL_ID'),
    ],
    'forgot_password' => [
        'discord_channel_id' => env('ADMIN_LOGS_FORGOT_PASSWORD_DISCORD_CHANNEL_ID'),
    ],
    'change_password' => [
        'discord_channel_id' => env('ADMIN_LOGS_CHANGE_PASSWORD_DISCORD_CHANNEL_ID'),
    ],
    'change_email' => [
        'discord_channel_id' => env('ADMIN_LOGS_CHANGE_EMAIL_DISCORD_CHANNEL_ID'),
    ],
    'ticket' => [
        'discord_channel_id' => env('ADMIN_LOGS_TICKET_DISCORD_CHANNEL_ID'),
    ],
];
