<?php

declare(strict_types=1);

use Domain\Shared\Http\Middleware\MetricsMiddleware;
use Domain\Shared\Services\MetricsService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

describe('MetricsMiddleware', function (): void {
    beforeEach(function (): void {
        $this->metricsService = Mockery::mock(MetricsService::class);
        $this->middleware = new MetricsMiddleware($this->metricsService);
    });

    it('increments http requests counter', function (): void {
        $request = Request::create('/api/users', 'GET');

        $this->metricsService
            ->shouldReceive('incrementCounter')
            ->once()
            ->with('http_requests_total');

        $this->metricsService
            ->shouldReceive('observeHistogram')
            ->once();

        $this->middleware->handle($request, fn (): \Illuminate\Http\Response => new Response('ok'));
    });

    it('records request duration', function (): void {
        $request = Request::create('/api/users', 'GET');

        $this->metricsService
            ->shouldReceive('incrementCounter')
            ->once();

        $this->metricsService
            ->shouldReceive('observeHistogram')
            ->once()
            ->withArgs(fn ($name, $duration, $labels): bool => $name === 'http_request_duration_seconds'
                && is_float($duration)
                && $labels['method'] === 'GET'
                && $labels['status'] === '200');

        $this->middleware->handle($request, fn (): \Illuminate\Http\Response => new Response('ok'));
    });

    it('normalizes paths with UUIDs', function (): void {
        $request = Request::create('/api/users/550e8400-e29b-41d4-a716-446655440000', 'GET');

        $this->metricsService->shouldReceive('incrementCounter')->once();

        $this->metricsService
            ->shouldReceive('observeHistogram')
            ->once()
            ->withArgs(fn ($name, $duration, $labels): bool => $labels['path'] === 'api/users/{id}');

        $this->middleware->handle($request, fn (): \Illuminate\Http\Response => new Response('ok'));
    });

    it('normalizes paths with numeric IDs', function (): void {
        $request = Request::create('/api/users/123/posts/456', 'GET');

        $this->metricsService->shouldReceive('incrementCounter')->once();

        $this->metricsService
            ->shouldReceive('observeHistogram')
            ->once()
            ->withArgs(fn ($name, $duration, $labels): bool => $labels['path'] === 'api/users/{id}/posts/{id}');

        $this->middleware->handle($request, fn (): \Illuminate\Http\Response => new Response('ok'));
    });
});
