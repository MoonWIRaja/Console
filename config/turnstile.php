<?php

return [
    /*
     * Enable or disable Cloudflare Turnstile validation.
     */
    'enabled' => env('TURNSTILE_ENABLED', false),

    /*
     * API endpoint for Turnstile verification.
     */
    'domain' => env('TURNSTILE_DOMAIN', 'https://challenges.cloudflare.com/turnstile/v0/siteverify'),

    /*
     * Turnstile secret and site keys.
     */
    'secret_key' => env('TURNSTILE_SECRET_KEY', ''),
    'site_key' => env('TURNSTILE_SITE_KEY', ''),

    /*
     * If enabled, verifies that the response hostname matches the request host.
     */
    'verify_domain' => env('TURNSTILE_VERIFY_DOMAIN', true),
];
