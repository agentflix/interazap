<?php

declare(strict_types=1);

namespace Domain\Shared\Http\Controllers;

use Domain\Shared\Services\HealthCheckService;
use Illuminate\Http\JsonResponse;

/**
 * Health Check Controller for deep system health monitoring.
 *
 * Returns status of all critical services:
 * - Database connection
 * - Redis connection
 * - Queue workers
 *
 * @category Controllers
 */
final class HealthController extends BaseController
{
    public function __construct(
        private readonly HealthCheckService $healthCheckService
    ) {}

    /**
     * GET /health
     *
     * Returns comprehensive health status of all services.
     *
     * @return JsonResponse Health status with 200 (healthy) or 503 (unhealthy).
     */
    public function __invoke(): JsonResponse
    {
        $health = $this->healthCheckService->check();

        $statusCode = $health['status'] === 'healthy' ? 200 : 503;

        return response()->json($health, $statusCode);
    }
}
