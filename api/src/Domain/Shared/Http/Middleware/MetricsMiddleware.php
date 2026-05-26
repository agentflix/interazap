<?php

declare(strict_types=1);

namespace Domain\Shared\Http\Middleware;

use Closure;
use Domain\Shared\Services\MetricsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Middleware para coleta de métricas de requisições HTTP.
 *
 * Registra contadores de total de requisições e histogramas de duração
 * no Prometheus, normalizando o path para evitar alta cardinalidade.
 */
final class MetricsMiddleware
{
    private const ROUTE_NORMALIZE_PATTERN = '/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i';

    public function __construct(
        private readonly MetricsService $metricsService
    ) {}

    /**
     * Processa a requisição e registra métricas de duração e total de chamadas.
     *
     * @param  Request  $request  Requisição HTTP.
     * @param  Closure  $next  Próximo middleware na cadeia.
     * @return Response Resposta original sem modificação.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);

        /** @var Response $response */
        $response = $next($request);

        $duration = microtime(true) - $startTime;

        try {
            // Increment total requests counter
            $this->metricsService->incrementCounter('http_requests_total');

            // Record request duration histogram
            $this->metricsService->observeHistogram('http_request_duration_seconds', $duration, [
                'method' => $request->method(),
                'path' => $this->normalizePath($request->path()),
                'status' => (string) $response->getStatusCode(),
            ]);
        } catch (Throwable $e) {
            Log::warning('metrics.recording_failed', ['error' => $e->getMessage()]);
        }

        return $response;
    }

    /**
     * Normaliza o path da requisição substituindo UUIDs e IDs numéricos por {id}.
     *
     * @param  string  $path  Path original da requisição.
     * @return string Path normalizado para uso como label Prometheus.
     */
    private function normalizePath(string $path): string
    {
        // Replace UUIDs
        $path = preg_replace(
            self::ROUTE_NORMALIZE_PATTERN,
            '{id}',
            $path
        );

        // Replace numeric IDs
        $path = preg_replace('/\/\d+/', '/{id}', $path);

        return $path ?? '/';
    }
}
