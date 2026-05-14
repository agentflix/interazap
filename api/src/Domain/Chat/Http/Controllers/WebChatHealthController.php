<?php

declare(strict_types=1);

namespace Domain\Chat\Http\Controllers;

use Domain\Shared\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Redis;

/**
 * Health check controller for Webchat module.
 *
 * @category Controllers
 */
final class WebChatHealthController extends BaseController
{
    /**
     * Health check endpoint for webchat module.
     *
     * GET /api/webchat/health
     *
     * @return JsonResponse Health status payload.
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
     * Verificar conexão com Redis.
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
