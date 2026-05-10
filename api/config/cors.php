<?php

declare(strict_types=1);

/**
 * CORS Configuration.
 *
 * In production, only whitelisted origins are allowed.
 * In development (local/testing), localhost origins are also permitted.
 */
$productionOrigins = [
    'https://app.interazap.com.br',
    'https://staging.interazap.com.br',
    'https://stage.app.interazap.com.br',
    'https://stage.api.interazap.com.br',
];

$developmentOrigins = [
    'http://localhost:4200',
    'http://localhost:5173',
    'http://localhost:8000',
    'http://localhost:3000',
];

// Parse additional origins from environment variable
$envOrigins = array_values(array_filter(array_map(
    trim(...),
    explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))
)));

// Mobile origins (Capacitor) — required for native iOS/Android shells.
// Allowed in ALL environments (including production) because the Capacitor
// runtime emits these origins from the app bundle, not from a public host.
$mobileOrigins = [
    'capacitor://localhost',
    'http://localhost',
];

// Combine origins based on environment
$allowedOrigins = match (env('APP_ENV', 'production')) {
    'local', 'testing' => array_merge($productionOrigins, $developmentOrigins, $mobileOrigins, $envOrigins),
    default => array_merge($productionOrigins, $mobileOrigins, $envOrigins),
};

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'storage/*'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => array_unique($allowedOrigins),

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Accept',
        'Authorization',
        'Content-Type',
        'X-Requested-With',
        'X-Trace-ID',
        'X-Session-Id',
    ],

    'exposed_headers' => [
        'X-Trace-ID',
    ],

    'max_age' => 86400, // 24 hours

    'supports_credentials' => true,

];
