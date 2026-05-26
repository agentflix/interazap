<?php

declare(strict_types=1);

namespace Domain\Platform\Http\Controllers;

use Domain\Platform\Services\QueueHealthService;
use Domain\Shared\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;

/**
 * Controlador de endpoints para monitoramento de saúde das filas.
 *
 * Os endpoints de health-check são autenticados via sanctum para proteger
 * informações operacionais sensíveis contra enumeração anônima.
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
     * Retorna o status de saúde de todas as filas monitoradas.
     *
     * @return JsonResponse Status de saúde com código HTTP 200 (saudável) ou 503 (problema).
     */
    public function index(): JsonResponse
    {
        $status = $this->healthService->getHealthStatus();

        $httpStatus = $status['healthy'] ? 200 : 503;

        return response()->json($status, $httpStatus);
    }

    /**
     * Retorna a configuração das filas registrada no sistema.
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
     * Retorna estatísticas detalhadas de uma fila específica.
     *
     * @param  string  $queue  Nome da fila.
     * @return JsonResponse Estatísticas da fila (tamanho e jobs atrasados).
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
