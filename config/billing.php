<?php

return [
    'currency' => env('BILLING_CURRENCY', 'MYR'),
    'invoice_lead_days' => (int) env('BILLING_INVOICE_LEAD_DAYS', 7),
    'suspend_grace_hours' => (int) env('BILLING_SUSPEND_GRACE_HOURS', 24),
    'delete_grace_hours' => (int) env('BILLING_DELETE_GRACE_HOURS', 72),
    'refund_suspend_hours' => (int) env('BILLING_REFUND_SUSPEND_HOURS', 5),
    'refund_delete_after_suspend_hours' => (int) env('BILLING_REFUND_DELETE_AFTER_SUSPEND_HOURS', 24),
    'renewal_reminder_offsets' => [7, 3, 1],
    'invoice_due_hours' => (int) env('BILLING_INVOICE_DUE_HOURS', 24),
    'gateway' => [
        'default' => 'fiuu',
    ],
    'fiuu' => [
        'provider' => 'fiuu',
        'enabled' => (bool) env('BILLING_FIUU_ENABLED', false),
        'sandbox' => (bool) env('BILLING_FIUU_SANDBOX', true),
        'merchant_id' => env('BILLING_FIUU_MERCHANT_ID'),
        'verify_key' => env('BILLING_FIUU_VERIFY_KEY'),
        'extended_vcode' => (bool) env('BILLING_FIUU_EXTENDED_VCODE', false),
        'secret_key' => env('BILLING_FIUU_SECRET_KEY'),
        'return_url' => env('BILLING_FIUU_RETURN_URL', rtrim((string) env('APP_URL', ''), '/') . '/fiuu-return.php'),
        'callback_url' => env('BILLING_FIUU_CALLBACK_URL'),
        'enabled_methods' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('BILLING_FIUU_ENABLED_METHODS', ''))
        ))),
        'checkout_urls' => [
            'production' => env('BILLING_FIUU_CHECKOUT_URL', 'https://pay.merchant.razer.com/RMS/pay'),
            'sandbox' => env('BILLING_FIUU_SANDBOX_CHECKOUT_URL', 'https://sandbox.merchant.razer.com/RMS/pay'),
        ],
        'requery_url' => env('BILLING_FIUU_REQUERY_URL'),
        'recurring_url' => env('BILLING_FIUU_RECURRING_URL'),
        'refund_url' => env('BILLING_FIUU_REFUND_URL'),
    ],
];
