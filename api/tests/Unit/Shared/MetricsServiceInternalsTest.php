<?php

declare(strict_types=1);

namespace Tests\Unit\Shared;

use Domain\Shared\Services\MetricsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;
use Prometheus\Storage\InMemory;
use RuntimeException;
use Tests\TestCase;

class MetricsServiceInternalsTest extends TestCase
{
    public function test_internal_collectors_return_zeroed_metrics_on_failures(): void
    {
        $service = new MetricsServiceHarness(new CollectorRegistry(new InMemory), new RenderTextFormat);

        Queue::shouldReceive('size')->andThrow(new RuntimeException('queue-down'));
        DB::shouldReceive('select')->andThrow(new RuntimeException('db-down'));
        Cache::shouldReceive('store')->andThrow(new RuntimeException('redis-down'));
        DB::shouldReceive('table')->andThrow(new RuntimeException('db-down-business'));

        $this->assertSame([
            'jobs_total' => 0,
            'jobs_pending' => 0,
            'jobs_failed' => 0,
        ], $service->queueMetrics());

        $this->assertSame([
            'connections' => 0,
        ], $service->databaseMetrics());

        $this->assertSame([
            'connected' => 0,
            'memory_used' => 0,
        ], $service->redisMetrics());

        $this->assertSame([
            'tickets' => [],
            'messages' => [],
            'negotiations_value' => 0.0,
            'negotiations_count' => 0,
        ], $service->businessMetrics());
    }

    public function test_redis_metrics_reports_connected_and_memory_used_on_success(): void
    {
        $service = new MetricsServiceHarness(new CollectorRegistry(new InMemory), new RenderTextFormat);

        Cache::shouldReceive('store')->once()->with('redis')->andReturn(new class
        {
            public function getStore(): object
            {
                return new class
                {
                    public function connection(): object
                    {
                        return new class
                        {
                            /** @return array<string, int> */
                            public function info(): array
                            {
                                return ['used_memory' => 8192];
                            }
                        };
                    }
                };
            }
        });

        $this->assertSame([
            'connected' => 1,
            'memory_used' => 8192,
        ], $service->redisMetrics());
    }
}

final class MetricsServiceHarness extends MetricsService
{
    /** @return array{jobs_total: int, jobs_pending: int, jobs_failed: int} */
    public function queueMetrics(): array
    {
        return $this->getQueueMetrics();
    }

    /** @return array{connections: int} */
    public function databaseMetrics(): array
    {
        return $this->getDatabaseMetrics();
    }

    /** @return array{connected: int, memory_used: int} */
    public function redisMetrics(): array
    {
        return $this->getRedisMetrics();
    }

    /** @return array{tickets: array<string, array{status: string, count: int}>, messages: array<string, array{direction: string, count: int}>, negotiations_value: float, negotiations_count: int} */
    public function businessMetrics(): array
    {
        return $this->getBusinessMetrics();
    }
}
