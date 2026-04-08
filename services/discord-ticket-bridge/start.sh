#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="/var/www/pterodactyl"
BRIDGE_DIR="${ROOT_DIR}/services/discord-ticket-bridge"

cd "${ROOT_DIR}"

eval "$(/usr/bin/php <<'PHP'
<?php
require 'vendor/autoload.php';

$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$vars = [
    'DISCORD_BOT_TOKEN' => (string) config('services.discord.bot_token', ''),
    'PANEL_INTERNAL_BASE_URL' => rtrim((string) config('app.url', ''), '/'),
    'TICKET_BRIDGE_SHARED_SECRET' => (string) config('tickets.bridge.shared_secret', ''),
    'DISCORD_RELAY_WEBHOOK_ID' => (string) config('tickets.discord.relay_webhook_id', ''),
    'DISCORD_ENABLE_GUILD_MEMBERS' => 'true',
    'DISCORD_ENABLE_MESSAGE_CONTENT' => 'true',
    'TICKET_BRIDGE_HEARTBEAT_SECONDS' => '30',
];

foreach ($vars as $key => $value) {
    echo 'export ' . $key . '=' . escapeshellarg($value) . PHP_EOL;
}
PHP
)"

cd "${BRIDGE_DIR}"
exec /usr/bin/node index.mjs
