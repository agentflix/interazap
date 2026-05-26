<?php

declare(strict_types=1);

namespace Domain\Shared\Http\Controllers;

use Domain\Shared\Services\MetricsService;
use Illuminate\Http\Response;

/**
 * Endpoint de métricas compatível com Prometheus.
 *
 * Expõe métricas da aplicação no formato texto do Prometheus,
 * incluindo contadores HTTP, histogramas de latência e métricas de negócio.
 */
final class MetricsController extends BaseController
{
    public function __construct(
        private readonly MetricsService $metricsService
    ) {}

    /**
     * GET /metrics
     *
     * Retorna todas as métricas no formato texto do Prometheus.
     *
     * @return Response Métricas formatadas com Content-Type text/plain.
     */
    public function __invoke(): Response
    {
        $metrics = $this->metricsService->collect();

        return response($metrics, 200, [
            'Content-Type' => 'text/plain; version=0.0.4; charset=utf-8',
        ]);
    }
}
