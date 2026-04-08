<?php

return [
    'enabled' => env('DOWN_DETECTOR_ENABLED', false),
    'discord' => [
        'channel_id' => env('DOWN_DETECTOR_DISCORD_CHANNEL_ID'),
    ],
    'monitor_nodes' => true,
    'monitor_servers' => true,
    'interval_seconds' => 60,
    'probe_timeout_ms' => 3000,
    'failure_threshold' => 2,
    'recovery_threshold' => 2,
    'node' => [
        'discord' => [
            'alert_channel_id' => env('DOWN_DETECTOR_NODE_ALERT_CHANNEL_ID'),
        ],
        'periodic_reports_enabled' => false,
        'periodic_report_interval_minutes' => 1440,
        'last_periodic_report_at' => null,
    ],
    'server' => [
        'discord' => [
            'alert_channel_id' => env('DOWN_DETECTOR_SERVER_ALERT_CHANNEL_ID'),
            'launcher_channel_id' => env('DOWN_DETECTOR_SERVER_LAUNCHER_CHANNEL_ID'),
            'launcher_message_id' => env('DOWN_DETECTOR_SERVER_LAUNCHER_MESSAGE_ID'),
        ],
    ],
    'last_run_at' => null,
    'last_run_summary' => [],
];
