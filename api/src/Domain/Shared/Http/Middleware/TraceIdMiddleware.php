<?php

declare(strict_types=1);

namespace Domain\Shared\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware for trace ID propagation.
 *
 * Generates or reads X-Trace-ID header and propagates it through:
 * - Log context
 * - Response headers
 */
final class TraceIdMiddleware
{
    public const HEADER_NAME = 'X-Trace-ID';

    public const CONTEXT_KEY = 'trace_id';

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $traceId = $request->header(self::HEADER_NAME) ?? Str::uuid()->toString();

        // Store in request for later access
        $request->attributes->set(self::CONTEXT_KEY, $traceId);

        // Add to log context
        Log::shareContext([
            self::CONTEXT_KEY => $traceId,
        ]);

        /** @var Response $response */
        $response = $next($request);

        // Add trace ID to response headers
        $response->headers->set(self::HEADER_NAME, $traceId);

        return $response;
    }
}
