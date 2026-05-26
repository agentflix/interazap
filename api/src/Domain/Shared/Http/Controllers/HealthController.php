<?php

declare(strict_types=1);

namespace Domain\Shared\Http\Controllers;

use Domain\Shared\Services\HealthCheckService;
use Illuminate\Http\JsonResponse;

/**
 * Endpoint de monitoramento profundo de saúde do sistema.
 *
 * Verifica a disponibilidade dos serviços críticos:
 * - Conexão com banco de dados
 * - Conexão com Redis
 * - Workers de fila
 */
final class HealthController extends BaseController
{
    public function __construct(
        private readonly HealthCheckService $healthCheckService
    ) {}

    /**
     * GET /health
     *
     * Retorna o status de saúde consolidado de todos os serviços.
     *
     * @return JsonResponse Status 200 (saudável) ou 503 (degradado/indisponível).
     */
    public function __invoke(): JsonResponse
    {
        $health = $this->healthCheckService->check();

        $statusCode = $health['status'] === 'healthy' ? 200 : 503;

        return response()->json($health, $statusCode);
    }
}
