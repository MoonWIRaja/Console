<?php

return [
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
];
