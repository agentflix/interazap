<?php

declare(strict_types=1);

use Domain\Platform\Services\QueueHealthService;

beforeEach(function (): void {
    $this->service = new QueueHealthService;
});

describe('QueueHealthService', function (): void {
    describe('getHealthStatus', function (): void {
        it('returns correct health status structure', function (): void {
            $status = $this->service->getHealthStatus();

            expect($status)
                ->toBeArray()
                ->toHaveKeys(['healthy', 'issues', 'queues', 'workers', 'stuck_jobs', 'thresholds', 'checked_at']);
        });

        it('includes threshold configuration', function (): void {
            $status = $this->service->getHealthStatus();

            expect($status['thresholds'])
                ->toBeArray()
                ->toHaveKeys(['max_queue_size', 'max_stuck_jobs']);
        });

        it('returns iso8601 timestamp for checked_at', function (): void {
            $status = $this->service->getHealthStatus();

            expect($status['checked_at'])
                ->toBeString()
                ->toContain('T');
        });

        it('reports issues when no workers are active', function (): void {
            // Force getWorkerCount() to return zero workers
            $mockService = Mockery::mock(QueueHealthService::class)->makePartial();
            $mockService->shouldReceive('getWorkerCount')->andReturn(0);
            $mockService->shouldReceive('getStuckJobsCount')->andReturn(0);
            $mockService->shouldReceive('getQueueStats')->andReturn([
                ['name' => 'default', 'size' => 0, 'delayed' => 0],
            ]);

            $status = $mockService->getHealthStatus();

            expect($status['healthy'])->toBeFalse();
            expect($status['issues'])->toContain('No active workers detected');
        });

        it('marks worker count as unknown when unavailable', function (): void {
            $mockService = Mockery::mock(QueueHealthService::class)->makePartial();
            $mockService->shouldReceive('getWorkerCount')->andReturn(-1);
            $mockService->shouldReceive('getStuckJobsCount')->andReturn(0);
            $mockService->shouldReceive('getQueueStats')->andReturn([
                ['name' => 'default', 'size' => 0, 'delayed' => 0],
            ]);

            $status = $mockService->getHealthStatus();

            expect($status['workers'])->toBe('unknown');
            expect($status['issues'])->not->toContain('No active workers detected');
        });
    });

    describe('getQueueStats', function (): void {
        it('returns stats for all default queues', function (): void {
            $stats = $this->service->getQueueStats();

            expect($stats)->toBeArray()->not->toBeEmpty();

            $queueNames = array_column($stats, 'name');
            expect($queueNames)->toContain('default', 'high', 'low', 'critical');
        });

        it('returns correct structure for each queue', function (): void {
            $stats = $this->service->getQueueStats();

            foreach ($stats as $queue) {
                expect($queue)
                    ->toBeArray()
                    ->toHaveKeys(['name', 'size', 'delayed']);

                expect($queue['size'])->toBeInt()->toBeGreaterThanOrEqual(0);
                expect($queue['delayed'])->toBeInt()->toBeGreaterThanOrEqual(0);
            }
        });
    });

    describe('getQueueSize', function (): void {
        it('returns integer for queue size', function (): void {
            $size = $this->service->getQueueSize('default');

            expect($size)->toBeInt()->toBeGreaterThanOrEqual(0);
        });

        it('returns 0 for non-existent queue', function (): void {
            $size = $this->service->getQueueSize('non-existent-queue-xyz');

            expect($size)->toBe(0);
        });
    });

    describe('getDelayedCount', function (): void {
        it('returns integer for delayed count', function (): void {
            $count = $this->service->getDelayedCount('default');

            expect($count)->toBeInt()->toBeGreaterThanOrEqual(0);
        });
    });

    describe('getWorkerCount', function (): void {
        it('returns integer for worker count', function (): void {
            $count = $this->service->getWorkerCount();

            expect($count)->toBeInt()->toBeGreaterThanOrEqual(-1);
        });
    });

    describe('getStuckJobsCount', function (): void {
        it('returns integer for stuck jobs count', function (): void {
            $count = $this->service->getStuckJobsCount();

            expect($count)->toBeInt()->toBeGreaterThanOrEqual(0);
        });
    });

    describe('getQueueConfig', function (): void {
        it('returns queue configuration from config', function (): void {
            $config = $this->service->getQueueConfig();

            expect($config)->toBeArray();
            expect($config)->toHaveKey('default');
        });

        it('includes timeout and tries for each queue', function (): void {
            $config = $this->service->getQueueConfig();

            foreach (['critical', 'high', 'default', 'low', 'ai'] as $queue) {
                if (isset($config[$queue])) {
                    expect($config[$queue])->toHaveKeys(['timeout', 'tries']);
                }
            }
        });
    });

    describe('setQueues', function (): void {
        it('allows setting custom queues to monitor', function (): void {
            $this->service->setQueues(['custom-queue-1', 'custom-queue-2']);
            $stats = $this->service->getQueueStats();

            expect($stats)->toHaveCount(2);
            expect($stats[0]['name'])->toBe('custom-queue-1');
            expect($stats[1]['name'])->toBe('custom-queue-2');
        });

        it('returns self for method chaining', function (): void {
            $result = $this->service->setQueues(['test']);

            expect($result)->toBeInstanceOf(QueueHealthService::class);
        });
    });
});

describe('QueueHealthService threshold detection', function (): void {
    it('reports queue size exceeds threshold', function (): void {
        config(['queue.health.max_queue_size' => 5]);

        $service = Mockery::mock(QueueHealthService::class)->makePartial();
        $service->shouldReceive('getQueueStats')->andReturn([
            ['name' => 'high', 'size' => 100, 'delayed' => 0],
        ]);
        $service->shouldReceive('getWorkerCount')->andReturn(1);
        $service->shouldReceive('getStuckJobsCount')->andReturn(0);

        $status = $service->getHealthStatus();

        expect($status['healthy'])->toBeFalse();
        expect($status['issues'])->toHaveCount(1);
        expect($status['issues'][0])->toContain('Queue "high" size (100) exceeds threshold (5)');
    });

    it('reports stuck jobs exceed threshold', function (): void {
        config(['queue.health.max_stuck_jobs' => 5]);

        $service = Mockery::mock(QueueHealthService::class)->makePartial();
        $service->shouldReceive('getQueueStats')->andReturn([
            ['name' => 'default', 'size' => 0, 'delayed' => 0],
        ]);
        $service->shouldReceive('getWorkerCount')->andReturn(1);
        $service->shouldReceive('getStuckJobsCount')->andReturn(15);

        $status = $service->getHealthStatus();

        expect($status['healthy'])->toBeFalse();
        expect($status['issues'][0])->toContain('Stuck jobs (15) exceeds threshold (5)');
    });

    it('reports healthy when all thresholds are within limits', function (): void {
        config([
            'queue.health.max_queue_size' => 1000,
            'queue.health.max_stuck_jobs' => 10,
        ]);

        $service = Mockery::mock(QueueHealthService::class)->makePartial();
        $service->shouldReceive('getQueueStats')->andReturn([
            ['name' => 'default', 'size' => 50, 'delayed' => 5],
        ]);
        $service->shouldReceive('getWorkerCount')->andReturn(4);
        $service->shouldReceive('getStuckJobsCount')->andReturn(0);

        $status = $service->getHealthStatus();

        expect($status['healthy'])->toBeTrue();
        expect($status['issues'])->toBeEmpty();
    });
});
