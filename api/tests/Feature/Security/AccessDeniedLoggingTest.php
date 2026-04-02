<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use Domain\Shared\Http\Middleware\LogAccessDeniedMiddleware;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class AccessDeniedLoggingTest extends TestCase
{
    public function test_middleware_logs_403_responses(): void
    {
        Log::shouldReceive('channel')
            ->with('access-denied')
            ->andReturnSelf();

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn (string $message, array $context): bool => $message === 'access.denied'
                && $context['action'] === 'GET'
                && $context['resource'] === 'test/resource'
            );

        $request = Request::create('/test/resource', 'GET');

        $middleware = new LogAccessDeniedMiddleware;

        $response = $middleware->handle($request, fn (): \Illuminate\Http\Response => new Response('Forbidden', 403));

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_middleware_does_not_log_non_403_responses(): void
    {
        Log::shouldReceive('channel')
            ->never();

        $request = Request::create('/test/resource', 'GET');

        $middleware = new LogAccessDeniedMiddleware;

        $response = $middleware->handle($request, fn (): \Illuminate\Http\Response => new Response('OK', 200));

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_middleware_extracts_reason_from_json_response(): void
    {
        Log::shouldReceive('channel')
            ->with('access-denied')
            ->andReturnSelf();

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn (string $message, array $context): bool => $message === 'access.denied'
                && $context['reason'] === 'Insufficient permissions'
            );

        $request = Request::create('/test/resource', 'POST');

        $middleware = new LogAccessDeniedMiddleware;

        $jsonContent = json_encode(['message' => 'Insufficient permissions']);

        $response = $middleware->handle($request, fn (): \Illuminate\Http\Response => new Response($jsonContent ?: '', 403));

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_middleware_includes_user_id_when_authenticated(): void
    {
        $user = \Domain\Auth\Models\AuthUser::factory()->create();

        Log::shouldReceive('channel')
            ->with('access-denied')
            ->andReturnSelf();

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn (string $message, array $context): bool => $message === 'access.denied'
                && $context['user_id'] === $user->id
            );

        $request = Request::create('/protected/resource', 'GET');
        $request->setUserResolver(fn () => $user);

        $middleware = new LogAccessDeniedMiddleware;

        $middleware->handle($request, fn (): \Illuminate\Http\Response => new Response('Forbidden', 403));
    }

    public function test_middleware_logs_tenant_id_from_request_attributes(): void
    {
        $tenantId = 'tenant-123';

        Log::shouldReceive('channel')
            ->with('access-denied')
            ->andReturnSelf();

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn (string $message, array $context): bool => $message === 'access.denied'
                && $context['tenant_id'] === $tenantId
            );

        $request = Request::create('/protected/resource', 'GET');
        $request->attributes->set('tenant_id', $tenantId);

        $middleware = new LogAccessDeniedMiddleware;

        $middleware->handle($request, fn (): \Illuminate\Http\Response => new Response('Forbidden', 403));
    }
}
