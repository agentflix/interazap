<?php

declare(strict_types=1);

namespace Domain\Chat\Http\Controllers;

use Domain\Shared\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Redis;

/**
 * Controller de verificação de saúde do módulo Webchat.
 *
 * @category Controllers
 */
final class WebChatHealthController extends BaseController
{
    /**
     * Retorna o status de saúde dos componentes do módulo Webchat.
     *
     * GET /api/webchat/health
     *
     * @return JsonResponse Payload com status geral e estado do Redis.
     */
    public function __invoke(): JsonResponse
    {
        $redisOk = $this->checkRedis();

        $status = $redisOk ? 'ok' : 'degraded';

        return response()->json([
            'status' => $status,
            'redis' => $redisOk,
            'timestamp' => now()->toIso8601String(),
        ], $redisOk ? 200 : 503);
    }

    /**
     * Verificar se a conexão com o Redis está operacional.
     *
     * @return bool True se o Redis responde corretamente ao ping.
     */
    private function checkRedis(): bool
    {
        try {
            Redis::connection('gateway')->ping();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
