<?php

declare(strict_types=1);

namespace Domain\Shared\Http\Controllers;

use Domain\Shared\Services\MetricsService;
use Illuminate\Http\Response;

/**
 * Prometheus-compatible metrics endpoint.
 *
 * Exposes application metrics in Prometheus text format.
 *
 * @category Controllers
 */
final class MetricsController extends BaseController
{
    public function __construct(
        private readonly MetricsService $metricsService
    ) {}

    /**
     * GET /metrics
     *
     * Returns metrics in Prometheus text format.
     *
     * @return Response Prometheus-formatted metrics with Content-Type header.
     */
    public function __invoke(): Response
    {
        $metrics = $this->metricsService->collect();

        return response($metrics, 200, [
            'Content-Type' => 'text/plain; version=0.0.4; charset=utf-8',
        ]);
    }
}
