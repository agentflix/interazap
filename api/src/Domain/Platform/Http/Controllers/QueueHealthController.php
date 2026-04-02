<?php

declare(strict_types=1);

namespace Domain\Platform\Http\Controllers;

use Domain\Platform\Services\QueueHealthService;
use Domain\Shared\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;

/**
 * Controller for queue health monitoring endpoints.
 *
 * Health-check endpoints intentionally unauthenticated for monitoring probes.
 */
final class QueueHealthController extends BaseController
{
    /**
     * @param  QueueHealthService  $healthService  Serviço de monitoramento de filas.
     */
    public function __construct(
        private readonly QueueHealthService $healthService,
    ) {}

    /**
     * Get queue health status.
     *
     * @return JsonResponse Status de saúde das filas.
     */
    public function index(): JsonResponse
    {
        $status = $this->healthService->getHealthStatus();

        $httpStatus = $status['healthy'] ? 200 : 503;

        return response()->json($status, $httpStatus);
    }

    /**
     * Get queue configuration.
     *
     * @return JsonResponse Configuração das filas.
     */
    public function config(): JsonResponse
    {
        return response()->json([
            'queues' => $this->healthService->getQueueConfig(),
        ]);
    }

    /**
     * Get detailed stats for a specific queue.
     *
     * @param  string  $queue  Nome da fila.
     * @return JsonResponse Estatísticas da fila.
     */
    public function show(string $queue): JsonResponse
    {
        return response()->json([
            'name' => $queue,
            'size' => $this->healthService->getQueueSize($queue),
            'delayed' => $this->healthService->getDelayedCount($queue),
        ]);
    }
}
