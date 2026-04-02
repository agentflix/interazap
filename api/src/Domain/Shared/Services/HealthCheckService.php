<?php

declare(strict_types=1);

namespace Domain\Shared\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Throwable;

/**
 * Service for performing deep health checks on all system services.
 */
final class HealthCheckService
{
    /**
     * Perform comprehensive health check on all services.
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
     * Check database connectivity.
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
     * Check Redis connectivity.
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
     * Check queue connectivity.
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
     * Determine overall system status based on individual service statuses.
     *
     * @param  array<string, array{status: string}>  $services
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
