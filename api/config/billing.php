<?php

declare(strict_types=1);

return [
    'streams' => [
        'payment_received' => [
            'name' => env('BILLING_STREAM_PAYMENT_RECEIVED', 'billing.payment_received'),
            'group' => env('BILLING_STREAM_GROUP', 'laravel-billing'),
            'consumer_prefix' => env('BILLING_STREAM_CONSUMER_PREFIX', 'billing-worker-'),
            'instance_webhook_token' => env('BILLING_STREAM_INSTANCE_TOKEN', 'billing-stream'),
            'realtime_event' => env('BILLING_STREAM_REALTIME_EVENT', 'billing.payment_received'),
            'batch_size' => (int) env('BILLING_STREAM_BATCH_SIZE', 10),
            'block_ms' => (int) env('BILLING_STREAM_BLOCK_MS', 2000),
        ],
    ],

    'delinquency' => [
        'enabled' => (bool) env('BILLING_DELINQUENCY_ENABLED', true),
        'grace_period_days' => (int) env('BILLING_GRACE_PERIOD_DAYS', 5),
        'purge_after_days' => (int) env('BILLING_PURGE_AFTER_DAYS', 30),
        'purge_warning_days_before' => (int) env('BILLING_PURGE_WARNING_DAYS', 5),

        'quiet_hours' => [
            'start' => (int) env('BILLING_COLLECTION_QUIET_HOURS_START', 18),
            'end' => (int) env('BILLING_COLLECTION_QUIET_HOURS_END', 9),
        ],

        'reminders' => [
            ['day' => 1, 'template' => 'friendly_reminder', 'channels' => ['email', 'whatsapp']],
            ['day' => 3, 'template' => 'urgent_reminder', 'channels' => ['email', 'whatsapp']],
            ['day' => 4, 'template' => 'lockout_warning', 'channels' => ['email', 'whatsapp']],
            ['day' => 5, 'template' => 'lockout_notice', 'channels' => ['email', 'whatsapp']],
            ['day' => 25, 'template' => 'purge_warning', 'channels' => ['email']],
        ],

        'lockout' => [
            'allowed_routes' => [
                ['method' => 'GET', 'pattern' => 'api/billing/invoices'],
                ['method' => 'POST', 'pattern' => 'api/billing/invoices/*/pay'],
                ['method' => 'GET', 'pattern' => 'api/billing/invoices/*/pix'],
                ['method' => 'GET', 'pattern' => 'api/billing/subscription'],
                ['method' => 'GET', 'pattern' => 'api/billing/plans'],
                ['method' => 'POST', 'pattern' => 'api/billing/plan-change'],
                ['method' => 'GET', 'pattern' => 'api/billing/plan-change/preview'],
                ['method' => 'POST', 'pattern' => 'api/auth/logout'],
                ['method' => 'GET', 'pattern' => 'api/auth/me'],
            ],
        ],

        'cache' => [
            'billing_status_prefix' => 'billing:tenant_status:',
            'billing_status_ttl' => 300,
        ],
    ],
];
