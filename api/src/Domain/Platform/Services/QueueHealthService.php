<?php

declare(strict_types=1);

namespace Domain\Platform\Services;

use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;

/**
 * Service for monitoring queue health.
 *
 * Provides methods to check queue sizes, worker status,
 * and stuck jobs for alerting purposes.
 */
class QueueHealthService
{
    /**
     * Default queues to monitor.
     *
     * @var array<string>
     */
    protected array $queues = ['critical', 'high', 'default', 'low', 'ai', 'media'];

    /**
     * Get health status for all queues.
     *
     * @return array<string, mixed>
     */
    public function getHealthStatus(): array
    {
        $config = config('queue.health', []);
        $maxQueueSize = $config['max_queue_size'] ?? 1000;
        $maxStuckJobs = $config['max_stuck_jobs'] ?? 10;

        $queues = $this->getQueueStats();
        $workerCount = $this->getWorkerCount();
        $workers = $workerCount >= 0 ? $workerCount : 'unknown';
        $stuckJobs = $this->getStuckJobsCount();

        $isHealthy = true;
        $issues = [];

        // Check queue sizes
        foreach ($queues as $queue) {
            if ($queue['size'] > $maxQueueSize) {
                $isHealthy = false;
                $issues[] = sprintf(
                    'Queue "%s" size (%d) exceeds threshold (%d)',
                    $queue['name'],
                    $queue['size'],
                    $maxQueueSize
                );
            }
        }

        // Check workers
        if ($workerCount === 0) {
            $isHealthy = false;
            $issues[] = 'No active workers detected';
        }

        // Check stuck jobs
        if ($stuckJobs > $maxStuckJobs) {
            $isHealthy = false;
            $issues[] = sprintf(
                'Stuck jobs (%d) exceeds threshold (%d)',
                $stuckJobs,
                $maxStuckJobs
            );
        }

        return [
            'healthy' => $isHealthy,
            'issues' => $issues,
            'queues' => $queues,
            'workers' => $workers,
            'stuck_jobs' => $stuckJobs,
            'thresholds' => [
                'max_queue_size' => $maxQueueSize,
                'max_stuck_jobs' => $maxStuckJobs,
            ],
            'checked_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Get statistics for each queue.
     *
     * @return array<int, array{name: string, size: int, delayed: int}>
     */
    public function getQueueStats(): array
    {
        $stats = [];

        foreach ($this->queues as $queueName) {
            $stats[] = [
                'name' => $queueName,
                'size' => $this->getQueueSize($queueName),
                'delayed' => $this->getDelayedCount($queueName),
            ];
        }

        return $stats;
    }

    /**
     * Get the number of jobs in a queue.
     */
    public function getQueueSize(string $queue): int
    {
        try {
            $connection = config('queue.default', 'redis');

            if ($connection === 'redis') {
                $prefix = config('database.redis.options.prefix', '');
                $key = $prefix.'queues:'.$queue;

                return (int) Redis::llen($key);
            }

            return Queue::size($queue);
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Get the number of delayed jobs in a queue.
     */
    public function getDelayedCount(string $queue): int
    {
        try {
            $connection = config('queue.default', 'redis');

            if ($connection === 'redis') {
                $prefix = config('database.redis.options.prefix', '');
                $key = $prefix.'queues:'.$queue.':delayed';

                return (int) Redis::zcard($key);
            }

            return 0;
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Get the number of active workers.
     *
     * @return int Number of workers or -1 when the runtime cannot determine it.
     */
    public function getWorkerCount(): int
    {
        try {
            if (class_exists(\Laravel\Horizon\Contracts\WorkloadRepository::class)) {
                $workload = app(\Laravel\Horizon\Contracts\WorkloadRepository::class);
                $supervisors = $workload->get();

                $total = 0;
                foreach ($supervisors as $supervisor) {
                    $total += $supervisor['processes'];
                }

                return $total;
            }

            return -1;
        } catch (\Throwable) {
            return -1;
        }
    }

    /**
     * Get the number of stuck jobs.
     * A job is considered stuck if it's been reserved for too long.
     */
    public function getStuckJobsCount(): int
    {
        try {
            $threshold = config('queue.health.stuck_threshold_seconds', 600);
            $stuckCount = 0;

            foreach ($this->queues as $queue) {
                $stuckCount += $this->getStuckJobsInQueue($queue, $threshold);
            }

            return $stuckCount;
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Get stuck jobs in a specific queue.
     */
    protected function getStuckJobsInQueue(string $queue, int $threshold): int
    {
        try {
            $connection = config('queue.default', 'redis');

            if ($connection === 'redis') {
                $prefix = config('database.redis.options.prefix', '');
                $key = $prefix.'queues:'.$queue.':reserved';
                $now = time();
                $cutoff = $now - $threshold;

                // Count jobs reserved before cutoff time
                return (int) Redis::zcount($key, '-inf', (string) $cutoff);
            }

            return 0;
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Get queue configuration.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getQueueConfig(): array
    {
        return config('queue.queues', []);
    }

    /**
     * Set the queues to monitor.
     *
     * @param  array<string>  $queues
     */
    public function setQueues(array $queues): self
    {
        $this->queues = $queues;

        return $this;
    }
}
