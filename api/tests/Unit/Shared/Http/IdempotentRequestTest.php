<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Http;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Shared\Http\Middleware\IdempotentRequest;
use Tests\TestCase;

class IdempotentRequestTest extends TestCase
{
    private IdempotentRequest $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new IdempotentRequest;
        Cache::flush();
    }

    #[Test]
    public function it_passes_through_get_requests(): void
    {
        $request = Request::create('/api/test', 'GET');
        $request->headers->set('X-Idempotency-Key', 'test-key');

        $response = $this->middleware->handle($request, fn (): \Illuminate\Http\Response => new Response('OK', 200));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertFalse($response->headers->has('X-Idempotency-Processed'));
    }

    #[Test]
    public function it_passes_through_requests_without_key(): void
    {
        $request = Request::create('/api/test', 'POST');

        $callCount = 0;
        $handler = function () use (&$callCount): \Illuminate\Http\Response {
            $callCount++;

            return new Response('OK', 200);
        };

        $this->middleware->handle($request, $handler);
        $this->middleware->handle($request, $handler);

        $this->assertSame(2, $callCount);
    }

    #[Test]
    public function it_processes_first_request_with_key(): void
    {
        $request = Request::create('/api/test', 'POST');
        $request->headers->set('X-Idempotency-Key', 'test-key-123');

        $response = $this->middleware->handle($request, fn (): \Illuminate\Http\Response => new Response('Created', 201));

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('true', $response->headers->get('X-Idempotency-Processed'));
        $this->assertFalse($response->headers->has('X-Idempotency-Cached'));
    }

    #[Test]
    public function it_returns_cached_response_for_duplicate_request(): void
    {
        $request1 = Request::create('/api/test', 'POST');
        $request1->headers->set('X-Idempotency-Key', 'duplicate-key');

        $callCount = 0;
        $handler = function () use (&$callCount): \Illuminate\Http\Response {
            $callCount++;

            return new Response('Created', 201);
        };

        $this->middleware->handle($request1, $handler);

        // Second request with same key
        $request2 = Request::create('/api/test', 'POST');
        $request2->headers->set('X-Idempotency-Key', 'duplicate-key');

        $response2 = $this->middleware->handle($request2, $handler);

        $this->assertSame(1, $callCount);
        $this->assertSame(201, $response2->getStatusCode());
        $this->assertSame('true', $response2->headers->get('X-Idempotency-Cached'));
    }

    #[Test]
    public function it_does_not_cache_error_responses(): void
    {
        $request = Request::create('/api/test', 'POST');
        $request->headers->set('X-Idempotency-Key', 'error-key');

        $callCount = 0;
        $handler = function () use (&$callCount): \Illuminate\Http\Response {
            $callCount++;

            return new Response('Error', 500);
        };

        $this->middleware->handle($request, $handler);
        $this->middleware->handle($request, $handler);

        $this->assertSame(2, $callCount);
    }

    #[Test]
    public function it_uses_different_keys_for_different_paths(): void
    {
        $request1 = Request::create('/api/resource/1', 'POST');
        $request1->headers->set('X-Idempotency-Key', 'same-key');

        $request2 = Request::create('/api/resource/2', 'POST');
        $request2->headers->set('X-Idempotency-Key', 'same-key');

        $callCount = 0;
        $handler = function () use (&$callCount): \Illuminate\Http\Response {
            $callCount++;

            return new Response('OK', 200);
        };

        $this->middleware->handle($request1, $handler);
        $this->middleware->handle($request2, $handler);

        $this->assertSame(2, $callCount);
    }

    #[Test]
    public function it_works_with_put_method(): void
    {
        $request = Request::create('/api/test', 'PUT');
        $request->headers->set('X-Idempotency-Key', 'put-key');

        $response = $this->middleware->handle($request, fn (): \Illuminate\Http\Response => new Response('Updated', 200));

        $this->assertSame('true', $response->headers->get('X-Idempotency-Processed'));
    }

    #[Test]
    public function it_works_with_patch_method(): void
    {
        $request = Request::create('/api/test', 'PATCH');
        $request->headers->set('X-Idempotency-Key', 'patch-key');

        $response = $this->middleware->handle($request, fn (): \Illuminate\Http\Response => new Response('Patched', 200));

        $this->assertSame('true', $response->headers->get('X-Idempotency-Processed'));
    }

    #[Test]
    public function it_includes_tenant_id_in_cache_key(): void
    {
        $request1 = Request::create('/api/test', 'POST');
        $request1->headers->set('X-Idempotency-Key', 'tenant-key');
        $request1->setUserResolver(static fn (): object => (object) ['tenant_id' => 'tenant-1']);

        $request2 = Request::create('/api/test', 'POST');
        $request2->headers->set('X-Idempotency-Key', 'tenant-key');
        $request2->setUserResolver(static fn (): object => (object) ['tenant_id' => 'tenant-2']);

        $callCount = 0;
        $handler = function () use (&$callCount): \Illuminate\Http\Response {
            $callCount++;

            return new Response('OK', 200);
        };

        $this->middleware->handle($request1, $handler);
        $this->middleware->handle($request2, $handler);

        // Different tenants should not share idempotency
        $this->assertSame(2, $callCount);
    }

    #[Test]
    public function it_isolates_anonymous_scope_by_ip_and_user_agent(): void
    {
        $request1 = Request::create('/api/test', 'POST', [], [], [], [
            'REMOTE_ADDR' => '10.1.0.1',
            'HTTP_USER_AGENT' => 'qa-agent-a',
        ]);
        $request1->headers->set('X-Idempotency-Key', 'anon-key');

        $request2 = Request::create('/api/test', 'POST', [], [], [], [
            'REMOTE_ADDR' => '10.1.0.2',
            'HTTP_USER_AGENT' => 'qa-agent-b',
        ]);
        $request2->headers->set('X-Idempotency-Key', 'anon-key');

        $callCount = 0;
        $handler = function () use (&$callCount): \Illuminate\Http\Response {
            $callCount++;

            return new Response('OK', 200);
        };

        $this->middleware->handle($request1, $handler);
        $this->middleware->handle($request2, $handler);

        // Anonymous requests from different fingerprints must not share idempotency cache.
        $this->assertSame(2, $callCount);
    }
}
