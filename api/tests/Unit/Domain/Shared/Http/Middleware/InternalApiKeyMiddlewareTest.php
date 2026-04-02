<?php

declare(strict_types=1);

use Domain\Shared\Http\Middleware\InternalApiKeyMiddleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

it('blocks request when api key is missing or invalid', function (): void {
    config()->set('services.gateway.api_key', 'expected-key');

    $middleware = new InternalApiKeyMiddleware;
    $request = Request::create('/internal/ai/context/1', 'GET');

    $response = $middleware->handle($request, static fn (Request $req): Response => new Response('ok', 200));

    expect($response->getStatusCode())->toBe(401)
        ->and((string) $response->getContent())->toContain('Unauthorized internal request.');
});

it('allows request when api key is valid', function (): void {
    config()->set('services.gateway.api_key', 'expected-key');

    $middleware = new InternalApiKeyMiddleware;
    $request = Request::create('/internal/ai/context/1', 'GET', [], [], [], [
        'HTTP_X_INTERNAL_API_KEY' => 'expected-key',
    ]);

    $response = $middleware->handle($request, static fn (Request $req): Response => new Response('ok', 200));

    expect($response->getStatusCode())->toBe(200)
        ->and((string) $response->getContent())->toBe('ok');
});
