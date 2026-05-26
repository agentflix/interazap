<?php

declare(strict_types=1);

namespace Domain\Shared\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware para propagação de trace ID entre serviços.
 *
 * Lê ou gera um UUID como X-Trace-ID e o propaga via:
 * - Contexto compartilhado de log (Log::shareContext)
 * - Header da resposta HTTP
 */
final class TraceIdMiddleware
{
    public const HEADER_NAME = 'X-Trace-ID';

    public const CONTEXT_KEY = 'trace_id';

    /**
     * Injeta o trace ID no contexto de log e nos headers da resposta.
     *
     * @param  Request  $request  Requisição HTTP.
     * @param  Closure  $next  Próximo middleware na cadeia.
     * @return Response Resposta com header X-Trace-ID adicionado.
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
