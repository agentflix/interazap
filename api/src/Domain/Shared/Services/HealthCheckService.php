<?php

declare(strict_types=1);

namespace Domain\Shared\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Throwable;

/**
 * Serviço de verificação profunda de saúde dos serviços de sistema.
 *
 * Testa individualmente banco de dados, Redis e fila, retornando o status
 * consolidado ('healthy', 'degraded' ou 'unhealthy') com latências medidas.
 */
final class HealthCheckService
{
    /**
     * Executa verificação completa de saúde em todos os serviços.
     *
     * @return array{status: string, timestamp: string, services: array<string, array{status: string, latency_ms?: float, message?: string}>}
     */
    public function check(): array
    {
        $services = [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'queue' => $this->checkQueue(),
        ];

        $overallStatus = $this->determineOverallStatus($services);

        return [
            'status' => $overallStatus,
            'timestamp' => now()->toIso8601String(),
            'services' => $services,
        ];
    }

    /**
     * Verifica a conectividade com o banco de dados.
     *
     * @return array{status: string, latency_ms?: float, message?: string}
     */
    public function checkDatabase(): array
    {
        $startTime = microtime(true);

        try {
            DB::connection()->getPdo();
            DB::select('SELECT 1');

            $latency = (microtime(true) - $startTime) * 1000;

            return [
                'status' => 'healthy',
                'latency_ms' => round($latency, 2),
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'unhealthy',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Verifica a conectividade com o Redis via escrita/leitura/deleção de chave.
     *
     * @return array{status: string, latency_ms?: float, message?: string}
     */
    public function checkRedis(): array
    {
        $startTime = microtime(true);

        try {
            $testKey = 'health_check_'.uniqid();
            Cache::store('redis')->put($testKey, 'ok', 10);
            $value = Cache::store('redis')->get($testKey);
            Cache::store('redis')->forget($testKey);

            if ($value !== 'ok') {
                return [
                    'status' => 'unhealthy',
                    'message' => 'Redis read/write verification failed',
                ];
            }

            $latency = (microtime(true) - $startTime) * 1000;

            return [
                'status' => 'healthy',
                'latency_ms' => round($latency, 2),
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'unhealthy',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Verifica a conectividade com a fila e retorna o tamanho atual da fila.
     *
     * @return array{status: string, latency_ms?: float, message?: string, queue_size?: int}
     */
    public function checkQueue(): array
    {
        $startTime = microtime(true);

        try {
            $queueConnection = config('queue.default');
            $queueSize = Queue::size();

            $latency = (microtime(true) - $startTime) * 1000;

            return [
                'status' => 'healthy',
                'latency_ms' => round($latency, 2),
                'queue_size' => $queueSize,
                'connection' => $queueConnection,
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'unhealthy',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Determina o status geral do sistema com base nos serviços individuais.
     *
     * Retorna 'healthy' se todos estiverem saudáveis, 'unhealthy' se todos
     * falharam, ou 'degraded' se apenas parte dos serviços estiver falhando.
     *
     * @param  array<string, array{status: string}>  $services  Mapa de nome para status do serviço.
     * @return string Status consolidado do sistema.
     */
    private function determineOverallStatus(array $services): string
    {
        $unhealthyCount = 0;

        foreach ($services as $service) {
            if ($service['status'] === 'unhealthy') {
                $unhealthyCount++;
            }
        }

        if ($unhealthyCount === 0) {
            return 'healthy';
        }

        if ($unhealthyCount === count($services)) {
            return 'unhealthy';
        }

        return 'degraded';
    }
}
