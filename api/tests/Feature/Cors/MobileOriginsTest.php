<?php

declare(strict_types=1);

/**
 * CORS Mobile Origins regression tests.
 *
 * Guarantees that capacitor://localhost and http://localhost are allowed
 * in every environment (including production) without opening CORS to
 * arbitrary origins.
 *
 * @see config/cors.php
 */

/**
 * Reload the cors config under a given APP_ENV so the match() expression
 * inside config/cors.php is re-evaluated for the desired environment.
 */
function reloadCorsConfigForEnv(string $appEnv): void
{
    putenv("APP_ENV={$appEnv}");
    $_ENV['APP_ENV'] = $appEnv;
    $_SERVER['APP_ENV'] = $appEnv;

    /** @var array<string, mixed> $cors */
    $cors = require base_path('config/cors.php');
    config(['cors' => $cors]);
}

beforeEach(function (): void {
    $this->originalEnv = env('APP_ENV', 'testing');
});

afterEach(function (): void {
    reloadCorsConfigForEnv((string) $this->originalEnv);
});

it('allows capacitor origin in production', function (): void {
    reloadCorsConfigForEnv('production');

    $response = $this->withHeaders([
        'Origin' => 'capacitor://localhost',
        'Access-Control-Request-Method' => 'GET',
    ])->options('/api/health');

    expect($response->headers->get('Access-Control-Allow-Origin'))
        ->toBe('capacitor://localhost');
});

it('allows http localhost origin in production', function (): void {
    reloadCorsConfigForEnv('production');

    $response = $this->withHeaders([
        'Origin' => 'http://localhost',
        'Access-Control-Request-Method' => 'GET',
    ])->options('/api/health');

    expect($response->headers->get('Access-Control-Allow-Origin'))
        ->toBe('http://localhost');
});

it('rejects arbitrary origin in production', function (): void {
    reloadCorsConfigForEnv('production');

    $response = $this->withHeaders([
        'Origin' => 'https://evil.com',
        'Access-Control-Request-Method' => 'GET',
    ])->options('/api/health');

    expect($response->headers->get('Access-Control-Allow-Origin'))
        ->not->toBe('https://evil.com');
});

it('preserves production origins', function (): void {
    reloadCorsConfigForEnv('production');

    $response = $this->withHeaders([
        'Origin' => 'https://app.interazap.com.br',
        'Access-Control-Request-Method' => 'GET',
    ])->options('/api/health');

    expect($response->headers->get('Access-Control-Allow-Origin'))
        ->toBe('https://app.interazap.com.br');
});
