<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for AI provider communication via the Gateway.
    |
    */
    'ai' => [
        'default_provider' => env('GATEWAY_AI_PROVIDER', 'openai'),
        'timeout_seconds' => (int) env('GATEWAY_AI_TIMEOUT', 180),
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Configuration
    |--------------------------------------------------------------------------
    |
    | Redis connection settings for Gateway communication.
    |
    */
    'redis' => [
        'connection' => env('GATEWAY_REDIS_CONNECTION', 'gateway'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Stream Configuration
    |--------------------------------------------------------------------------
    |
    | Redis Streams names and prefixes for Gateway communication.
    |
    */
    'streams' => [
        'ai_request' => 'ai.run.request',
        'ai_response' => 'ai.run.response',
        'ai_response_prefix' => 'ai.run.response:',

        'whatsapp_request' => 'whatsapp.message.request',
        'whatsapp_response_prefix' => 'whatsapp.message.response:',

        'payment_request' => 'payment.charge.request',
        'payment_response_prefix' => 'payment.charge.response:',
    ],

];
