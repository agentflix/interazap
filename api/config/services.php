<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'gateway' => [
        'url' => env('GATEWAY_URL', 'http://gateway:3000'),
        'api_key' => env('GATEWAY_INTERNAL_API_KEY'),
        'secret' => env('GATEWAY_SECRET'),
        'timeout' => (int) env('GATEWAY_TIMEOUT', 3),
        'retry_attempts' => (int) env('GATEWAY_RETRY_ATTEMPTS', 3),
        'retry_delay_ms' => (int) env('GATEWAY_RETRY_DELAY_MS', 150),
        'realtime_pubsub_enabled' => (bool) env('REALTIME_PUBSUB_ENABLED', true),
        'realtime_http_fallback_enabled' => (bool) env('REALTIME_HTTP_FALLBACK_ENABLED', true),
    ],

    'channels' => [
        'webhook_base_url' => env('CHANNELS_WEBHOOK_BASE_URL', env('APP_URL', 'http://localhost:3000')),
    ],

    'openai' => [
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
    ],

    'uazapi' => [],

    // Global HTTP retry delay (in ms) - can be overridden in tests
    'http_retry_delay_ms' => (int) env('HTTP_RETRY_DELAY_MS', 1000),

    'whatsapp' => [
        'use_real_provider' => (bool) env('WHATSAPP_USE_REAL_PROVIDER', false),
        'circuit_breaker' => [
            'failure_threshold' => (int) env('WHATSAPP_CB_FAILURE_THRESHOLD', 5),
            'success_threshold' => (int) env('WHATSAPP_CB_SUCCESS_THRESHOLD', 2),
            'open_timeout' => (int) env('WHATSAPP_CB_OPEN_TIMEOUT', 60),
        ],
    ],

    'asaas' => [
        'base_url' => env('ASAAS_BASE_URL', 'https://sandbox.asaas.com/api/v3'),
        'api_key' => env('ASAAS_API_KEY', ''),
        'webhook_token' => env('ASAAS_WEBHOOK_TOKEN'),
        'timeout' => (int) env('ASAAS_HTTP_TIMEOUT', 10),
    ],

    'lookup' => [
        'timeout' => (int) env('LOOKUP_HTTP_TIMEOUT', 5),
        'viacep_url' => env('VIACEP_URL', 'https://viacep.com.br/ws'),
        'receita_ws_url' => env('RECEITA_WS_URL', 'https://www.receitaws.com.br/v1/cnpj'),
    ],

];
