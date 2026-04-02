<?php

declare(strict_types=1);

namespace Shared\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Middleware for idempotent API requests.
 *
 * Uses the X-Idempotency-Key header to ensure requests
 * are only processed once. Subsequent requests with the
 * same key return the cached response.
 */
class IdempotentRequest
{
    /**
     * The header name for idempotency key.
     */
    public const HEADER_NAME = 'X-Idempotency-Key';

    /**
     * Cache prefix for idempotency responses.
     */
    protected string $cachePrefix = 'idempotent_request';

    /**
     * Default TTL in seconds (24 hours).
     */
    protected int $defaultTtl = 86400;

    /**
     * HTTP methods that require idempotency.
     *
     * @var array<string>
     */
    protected array $idempotentMethods = ['POST', 'PUT', 'PATCH'];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        // Only apply to modifying requests
        if (! in_array($request->method(), $this->idempotentMethods, true)) {
            return $next($request);
        }

        $idempotencyKey = $request->header(self::HEADER_NAME);

        // If no key provided, proceed normally
        if ($idempotencyKey === null) {
            return $next($request);
        }

        $cacheKey = $this->buildCacheKey($request, $idempotencyKey);

        // Check if we have a cached response
        $cachedResponse = Cache::get($cacheKey);
        if ($cachedResponse !== null) {
            return $this->buildResponseFromCache($cachedResponse);
        }

        // Process the request
        /** @var SymfonyResponse $response */
        $response = $next($request);

        // Only cache successful responses
        if ($response->isSuccessful()) {
            $this->cacheResponse($cacheKey, $response);
        }

        // Add header to indicate idempotency was processed
        $response->headers->set('X-Idempotency-Processed', 'true');

        return $response;
    }

    /**
     * Build the cache key for the request.
     */
    protected function buildCacheKey(Request $request, string $idempotencyKey): string
    {
        $scope = $this->resolveRequestScope($request);
        $method = strtoupper($request->method());

        return sprintf(
            '%s:%s:%s:%s:%s',
            $this->cachePrefix,
            $scope,
            $method,
            $request->path(),
            $idempotencyKey
        );
    }

    protected function resolveRequestScope(Request $request): string
    {
        $tenantId = data_get($request->user(), 'tenant_id');

        if (is_scalar($tenantId) && (string) $tenantId !== '') {
            return (string) $tenantId;
        }

        $ipAddress = $request->ip() ?? 'unknown-ip';
        $userAgent = $request->userAgent() ?? 'unknown-agent';
        $anonymousFingerprint = hash('sha256', $ipAddress.'|'.$userAgent);

        return 'anon:'.$anonymousFingerprint;
    }

    /**
     * Cache the response for future requests.
     */
    protected function cacheResponse(string $cacheKey, SymfonyResponse $response): void
    {
        $data = [
            'status' => $response->getStatusCode(),
            'headers' => $response->headers->all(),
            'content' => $response->getContent(),
            'cached_at' => now()->toIso8601String(),
        ];

        Cache::put($cacheKey, $data, $this->defaultTtl);
    }

    /**
     * Build a response from cached data.
     *
     * @param  array<string, mixed>  $cached
     */
    protected function buildResponseFromCache(array $cached): Response
    {
        $response = new Response(
            $cached['content'],
            $cached['status'],
            $cached['headers']
        );

        $response->headers->set('X-Idempotency-Cached', 'true');
        $response->headers->set('X-Idempotency-Cached-At', $cached['cached_at']);

        return $response;
    }
}
