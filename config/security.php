<?php

return [
    /*
     * Master switch for the custom Security Center runtime.
     */
    'enabled' => env('SECURITY_CENTER_ENABLED', true),

    /*
     * Cache store used for auth risk and lock records.
     * Set to null to use the default cache store.
     */
    'cache_store' => env('SECURITY_CACHE_STORE'),

    /*
     * Trusted IPs (or CIDRs) that should bypass aggressive auth checks.
     */
    'trusted_ips' => array_values(array_filter(array_map('trim', explode(',', env('SECURITY_TRUSTED_IPS', ''))))),

    /*
     * Security Center trusted networks used by custom runtime deny/allow logic.
     */
    'trusted_networks' => array_values(array_filter(array_map('trim', explode(',', env('SECURITY_TRUSTED_NETWORKS', ''))))),

    /*
     * Emergency break-glass bypass settings.
     */
    'break_glass' => [
        'trusted_networks' => array_values(array_filter(array_map('trim', explode(',', env('SECURITY_BREAK_GLASS_TRUSTED_NETWORKS', ''))))),
        'emergency_token' => env('SECURITY_BREAK_GLASS_EMERGENCY_TOKEN'),
    ],

    /*
     * Risk scoring thresholds and lock timings.
     */
    'risk' => [
        'challenge_threshold' => (int) env('SECURITY_RISK_CHALLENGE_THRESHOLD', 10),
        'lock_short_threshold' => (int) env('SECURITY_RISK_LOCK_SHORT_THRESHOLD', 18),
        'lock_long_threshold' => (int) env('SECURITY_RISK_LOCK_LONG_THRESHOLD', 30),
        'lock_short_minutes' => (int) env('SECURITY_RISK_LOCK_SHORT_MINUTES', 15),
        'lock_long_minutes' => (int) env('SECURITY_RISK_LOCK_LONG_MINUTES', 360),
        'decay_seconds' => (int) env('SECURITY_RISK_DECAY_SECONDS', 86400),
    ],

    /*
     * Honeypot form behavior.
     */
    'honeypot' => [
        'fields' => ['website', 'company'],
        'min_fill_seconds' => (int) env('SECURITY_HONEYPOT_MIN_FILL_SECONDS', 2),
        'timing_field' => 'form_rendered_at',
        'delay_ms_min' => 350,
        'delay_ms_max' => 900,
    ],

    /*
     * Alerting controls.
     */
    'alerts' => [
        'email' => env('SECURITY_ALERT_EMAIL', env('MAIL_FROM_ADDRESS')),
        'cooldown_seconds' => (int) env('SECURITY_ALERT_COOLDOWN_SECONDS', 300),
    ],

    /*
     * Runtime denylist and temporary hold controls.
     */
    'runtime' => [
        'ip_deny_minutes' => (int) env('SECURITY_RUNTIME_IP_DENY_MINUTES', 60),
        'fingerprint_deny_minutes' => (int) env('SECURITY_RUNTIME_FINGERPRINT_DENY_MINUTES', 60),
        'route_hold_minutes' => (int) env('SECURITY_RUNTIME_ROUTE_HOLD_MINUTES', 15),
    ],

    /*
     * Security Center protection modules.
     */
    'auth' => [
        'enabled' => env('SECURITY_AUTH_ENABLED', true),
        'auto_challenge' => env('SECURITY_AUTH_AUTO_CHALLENGE', true),
        'auto_temp_block' => env('SECURITY_AUTH_AUTO_TEMP_BLOCK', true),
    ],

    'api' => [
        'enabled' => env('SECURITY_API_ENABLED', true),
        'revoke_token_on_ip_anomaly' => env('SECURITY_API_REVOKE_TOKEN_ON_IP_ANOMALY', true),
    ],

    'bridge' => [
        'enabled' => env('SECURITY_BRIDGE_ENABLED', true),
    ],

    'webhook' => [
        'enabled' => env('SECURITY_WEBHOOK_ENABLED', true),
    ],

    'upload' => [
        'enabled' => env('SECURITY_UPLOAD_ENABLED', true),
        'quarantine_on_suspicious' => env('SECURITY_UPLOAD_QUARANTINE_ON_SUSPICIOUS', true),
        'suspicious_extensions' => array_values(array_filter(array_map(
            'trim',
            explode(',', env('SECURITY_UPLOAD_SUSPICIOUS_EXTENSIONS', 'php,phtml,phar,sh,bash,bat,cmd,exe,msi,com,scr,dll,so,jar,js,ps1,vbs,hta,py,pl,rb'))
        ))),
    ],

    'origin' => [
        'enabled' => env('SECURITY_ORIGIN_ENABLED', true),
        'not_controllable_threshold' => (int) env('SECURITY_ORIGIN_NOT_CONTROLLABLE_THRESHOLD', 5000),
    ],

    'agent' => [
        'enabled' => env('SECURITY_AGENT_ENABLED', true),
        'heartbeat_ttl_seconds' => (int) env('SECURITY_AGENT_HEARTBEAT_TTL_SECONDS', 180),
        'clock_skew_seconds' => (int) env('SECURITY_AGENT_CLOCK_SKEW_SECONDS', 90),
        'secret_rotation_grace_seconds' => (int) env('SECURITY_AGENT_SECRET_ROTATION_GRACE_SECONDS', 3600),
        'nonce_ttl_seconds' => (int) env('SECURITY_AGENT_NONCE_TTL_SECONDS', 300),
    ],

    'retention' => [
        'event_days' => (int) env('SECURITY_RETENTION_EVENT_DAYS', 30),
        'incident_days' => (int) env('SECURITY_RETENTION_INCIDENT_DAYS', 90),
    ],

    /*
     * Origins allowed to embed the panel inside an iframe.
     * The default "auto" value derives from APP_URL and allows the same root
     * domain, including sibling subdomains.
     *
     * Example:
     * SECURITY_FRAME_ALLOWED_ORIGINS=auto
     * SECURITY_FRAME_ALLOWED_ORIGINS=self,https://app.example.com,https://billing.example.com
     */
    'framing' => [
        'allowed_origins' => array_values(array_filter(array_map(
            'trim',
            explode(',', env('SECURITY_FRAME_ALLOWED_ORIGINS', 'auto'))
        ))),
    ],
];
