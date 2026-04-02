<?php

declare(strict_types=1);

use Domain\Shared\Http\Middleware\TraceIdMiddleware;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

describe('TraceIdMiddleware', function (): void {
    beforeEach(function (): void {
        $this->middleware = new TraceIdMiddleware;
    });

    it('generates trace id when not provided in request', function (): void {
        $request = Request::create('/test', 'GET');

        Log::shouldReceive('shareContext')->once();

        $response = $this->middleware->handle($request, fn (): \Illuminate\Http\Response => new Response('ok'));

        $traceId = $request->attributes->get('trace_id');

        expect($traceId)->toBeString();
        expect($traceId)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i');
        expect($response->headers->get('X-Trace-ID'))->toBe($traceId);
    });

    it('uses existing trace id from request header', function (): void {
        $existingTraceId = 'existing-trace-id-12345';
        $request = Request::create('/test', 'GET');
        $request->headers->set('X-Trace-ID', $existingTraceId);

        Log::shouldReceive('shareContext')->once()->withArgs(fn ($context): bool => $context['trace_id'] === $existingTraceId);

        $response = $this->middleware->handle($request, fn (): \Illuminate\Http\Response => new Response('ok'));

        expect($request->attributes->get('trace_id'))->toBe($existingTraceId);
        expect($response->headers->get('X-Trace-ID'))->toBe($existingTraceId);
    });

    it('adds trace id to log context', function (): void {
        $request = Request::create('/test', 'GET');

        Log::shouldReceive('shareContext')->once()->withArgs(fn ($context): bool => isset($context['trace_id']));

        $this->middleware->handle($request, fn (): \Illuminate\Http\Response => new Response('ok'));
    });

    it('propagates trace id in response headers', function (): void {
        $request = Request::create('/test', 'GET');

        Log::shouldReceive('shareContext')->once();

        $response = $this->middleware->handle($request, fn (): \Illuminate\Http\Response => new Response('ok'));

        expect($response->headers->has('X-Trace-ID'))->toBeTrue();
    });
});
