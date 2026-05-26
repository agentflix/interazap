<?php

declare(strict_types=1);

namespace Domain\Platform\Services;

use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;

/**
 * Serviço de monitoramento de saúde das filas.
 *
 * Provê métodos para verificar tamanhos de filas, status de workers
 * e jobs travados para fins de alertas e diagnóstico.
 */
class QueueHealthService
{
    /**
     * Filas padrão a monitorar.
     *
     * @var array<string>
     */
    protected array $queues = ['critical', 'high', 'default', 'low', 'ai', 'media'];

    /**
     * Retorna o status de saúde de todas as filas monitoradas.
     *
     * @return array<string, mixed> Status agregado com indicadores de saúde, filas, workers e jobs travados.
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
     * Retorna estatísticas individuais de tamanho e jobs atrasados para cada fila.
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
     * Retorna a quantidade de jobs aguardando processamento em uma fila.
     *
     * @param  string  $queue  Nome da fila.
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
     * Retorna a quantidade de jobs atrasados (scheduled/delayed) em uma fila.
     *
     * @param  string  $queue  Nome da fila.
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
     * Retorna o número de workers ativos via Horizon ou -1 quando não é possível determinar.
     *
     * @return int Número de workers ativos ou -1 se indeterminado.
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
     * Retorna o número total de jobs travados em todas as filas.
     *
     * Um job é considerado travado quando está reservado há mais tempo que o threshold configurado.
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
     * Retorna a quantidade de jobs travados em uma fila específica.
     *
     * @param  string  $queue  Nome da fila.
     * @param  int  $threshold  Tempo em segundos para considerar um job travado.
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
     * Retorna a configuração das filas definida no arquivo de configuração.
     *
     * @return array<string, array<string, mixed>> Configuração das filas.
     */
    public function getQueueConfig(): array
    {
        return config('queue.queues', []);
    }

    /**
     * Define as filas a serem monitoradas.
     *
     * @param  array<string>  $queues  Lista de nomes de filas.
     * @return static Instância atual para encadeamento.
     */
    public function setQueues(array $queues): self
    {
        $this->queues = $queues;

        return $this;
    }
}
