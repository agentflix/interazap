<?php

declare(strict_types=1);

$statefulDefault = env('APP_URL') ? parse_url((string) env('APP_URL'), PHP_URL_HOST) : '';

return [

    /*
    |--------------------------------------------------------------------------
    | Sanctum Stateful Domains
    |--------------------------------------------------------------------------
    */

    'stateful' => array_values(array_filter(array_map(
        trim(...),
        explode(',', (string) env('SANCTUM_STATEFUL_DOMAINS', (string) $statefulDefault))
    ))),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Guards
    |--------------------------------------------------------------------------
    */

    'guard' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Middleware
    |--------------------------------------------------------------------------
    */

    'middleware' => [
        'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies' => App\Http\Middleware\EncryptCookies::class,
        'validate_csrf_token' => App\Http\Middleware\VerifyCsrfToken::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Expiration Minutes
    |--------------------------------------------------------------------------
    |
    | Define o tempo de expiração dos tokens gerados.
    | Default: 1440 minutes (24 hours)
    |
    */

    'expiration' => env('SANCTUM_EXPIRATION', 1440),

    /*
    |--------------------------------------------------------------------------
    | Token Model
    |--------------------------------------------------------------------------
    |
    | Modelo e tabela customizados para tokens com prefixo auth_.
    |
    */

    'personal_access_token_model' => Domain\Auth\Models\AuthPersonalAccessToken::class,
];
