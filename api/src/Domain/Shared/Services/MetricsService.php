<?php

declare(strict_types=1);

namespace Domain\Shared\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Prometheus\CollectorRegistry;
use Prometheus\Counter;
use Prometheus\Gauge;
use Prometheus\Histogram;
use Prometheus\RenderTextFormat;
use Prometheus\Storage\InMemory;
use Throwable;

/**
 * Service responsible for recording and rendering Prometheus metrics.
 */
class MetricsService
{
    private const HISTOGRAM_BUCKETS = [0.05, 0.1, 0.25, 0.5, 1.0, 2.5, 5.0, 10.0];

    private CollectorRegistry $registry;

    private RenderTextFormat $renderer;

    /** @var array<string, Counter> */
    private array $counters = [];

    /** @var array<string, Gauge> */
    private array $gauges = [];

    /** @var array<string, Histogram> */
    private array $histograms = [];

    public function __construct(?CollectorRegistry $registry = null, ?RenderTextFormat $renderer = null)
    {
        $this->registry = $registry ?? app(CollectorRegistry::class);
        $this->renderer = $renderer ?? new RenderTextFormat;
    }

    /**
     * Render all metrics (persistent + snapshot) in Prometheus format.
     */
    public function collect(): string
    {
        $snapshotRegistry = new CollectorRegistry(new InMemory);

        $this->ensureHttpBaseline();

        $this->recordAppInfo($snapshotRegistry);
        $this->recordQueueMetrics($snapshotRegistry);
        $this->recordDatabaseMetrics($snapshotRegistry);
        $this->recordRedisMetrics($snapshotRegistry);
        $this->recordSystemMetrics($snapshotRegistry);
        $this->recordBusinessMetrics($snapshotRegistry);

        $samples = array_merge(
            $this->registry->getMetricFamilySamples(),
            $snapshotRegistry->getMetricFamilySamples()
        );

        return $this->renderer->render($samples);
    }

    /**
     * Increment a dynamic counter metric.
     *
     * @param  array<string, string>  $labels
     */
    public function incrementCounter(string $name, int $value = 1, array $labels = []): void
    {
        $counter = $this->counter($name, 'Dynamic application counter', array_keys($labels));
        $counter->incBy($value, array_values($labels));
    }

    /**
     * Set a dynamic gauge metric.
     *
     * @param  array<string, string>  $labels
     */
    public function setGauge(string $name, float $value, array $labels = []): void
    {
        $gauge = $this->gauge($name, 'Dynamic application gauge', array_keys($labels));
        $gauge->set($value, array_values($labels));
    }

    /**
     * Observe a value for a dynamic histogram metric.
     *
     * @param  array<string, string>  $labels
     */
    public function observeHistogram(string $name, float $value, array $labels = []): void
    {
        $histogram = $this->histogram(
            $name,
            'Dynamic application histogram',
            array_keys($labels),
            self::HISTOGRAM_BUCKETS
        );

        $histogram->observe($value, array_values($labels));
    }

    /**
     * Record webhook processing duration (Autopilot Phase 4).
     *
     * @param  array<string, string>  $labels
     */
    public function recordAutopilotWebhookDuration(float $durationSeconds, array $labels = []): void
    {
        $histogram = $this->histogram(
            'autopilot_webhook_duration_seconds',
            'Autopilot webhook processing duration in seconds',
            array_keys($labels),
            [0.05, 0.1, 0.25, 0.5, 1.0, 2.5, 5.0, 10.0]
        );

        $histogram->observe($durationSeconds, array_values($labels));
    }

    /**
     * Increment guardrail block counter (Autopilot Phase 4).
     *
     * @param  array<string, string>  $labels
     */
    public function recordAutopilotGuardrailBlock(array $labels = []): void
    {
        $counter = $this->counter(
            'autopilot_guardrail_blocks_total',
            'Total guardrail blocks',
            array_keys($labels)
        );

        $counter->incBy(1, array_values($labels));
    }

    /**
     * Set budget usage ratio gauge (Autopilot Phase 4).
     *
     * @param  array<string, string>  $labels
     */
    public function setAutopilotBudgetUsageRatio(float $ratio, array $labels = []): void
    {
        $gauge = $this->gauge(
            'autopilot_budget_usage_ratio',
            'Autopilot budget usage ratio (0.0-1.0)',
            array_keys($labels)
        );

        $gauge->set($ratio, array_values($labels));
    }

    /**
     * Record run duration observed in autopilot execution lifecycle.
     *
     * @param  array<string, string>  $labels
     */
    public function recordAutopilotRunDuration(float $seconds, array $labels = []): void
    {
        $histogram = $this->histogram(
            'autopilot_run_duration_seconds',
            'Autopilot run duration in seconds',
            array_keys($labels),
            [0.1, 0.5, 1.0, 2.5, 5.0, 10.0, 30.0, 60.0, 120.0]
        );

        $histogram->observe($seconds, array_values($labels));
    }

    /**
     * Record tool iterations count observed in a run.
     *
     * @param  array<string, string>  $labels
     */
    public function recordAutopilotToolIterations(int $count, array $labels = []): void
    {
        $histogram = $this->histogram(
            'autopilot_tool_iterations',
            'Autopilot tool iteration count per run',
            array_keys($labels),
            [0, 1, 2, 3, 5, 8, 13, 21]
        );

        $histogram->observe((float) $count, array_values($labels));
    }

    /**
     * Record time spent waiting for approval resolution.
     *
     * @param  array<string, string>  $labels
     */
    public function recordAutopilotApprovalWaitTime(float $seconds, array $labels = []): void
    {
        $histogram = $this->histogram(
            'autopilot_approval_wait_time_seconds',
            'Autopilot approval wait time in seconds',
            array_keys($labels),
            [1, 5, 10, 30, 60, 300, 900, 3600, 86400]
        );

        $histogram->observe($seconds, array_values($labels));
    }

    /**
     * Record lock contention events on message dispatch idempotency lock.
     *
     * @param  array<string, string>  $labels
     */
    public function recordAutopilotLockContention(int $count = 1, array $labels = []): void
    {
        $counter = $this->counter(
            'autopilot_lock_contention_total',
            'Total autopilot lock contention events',
            array_keys($labels)
        );

        $counter->incBy($count, array_values($labels));
    }

    private function recordAppInfo(CollectorRegistry $registry): void
    {
        $gauge = $registry->getOrRegisterGauge(
            'interazap',
            'app_info',
            'Application information',
            ['version', 'env']
        );

        $gauge->set(1, [
            (string) config('app.version', '1.0.0'),
            (string) config('app.env', 'production'),
        ]);
    }

    private function recordQueueMetrics(CollectorRegistry $registry): void
    {
        $metrics = $this->getQueueMetrics();

        $registry->getOrRegisterGauge('interazap', 'queue_jobs_total', 'Total queue jobs tracked')
            ->set($metrics['jobs_total']);
        $registry->getOrRegisterGauge('interazap', 'queue_jobs_pending', 'Pending jobs in queue')
            ->set($metrics['jobs_pending']);
        $registry->getOrRegisterGauge('interazap', 'queue_jobs_failed_total', 'Failed queue jobs')
            ->set($metrics['jobs_failed']);
    }

    private function recordDatabaseMetrics(CollectorRegistry $registry): void
    {
        $metrics = $this->getDatabaseMetrics();

        $registry->getOrRegisterGauge('interazap', 'database_connections_active', 'Active database connections')
            ->set($metrics['connections']);
    }

    private function recordRedisMetrics(CollectorRegistry $registry): void
    {
        $metrics = $this->getRedisMetrics();

        $registry->getOrRegisterGauge('interazap', 'redis_connected', 'Redis connection status')
            ->set($metrics['connected']);
        $registry->getOrRegisterGauge('interazap', 'redis_memory_used_bytes', 'Redis memory usage in bytes')
            ->set($metrics['memory_used']);
    }

    private function recordSystemMetrics(CollectorRegistry $registry): void
    {
        $registry->getOrRegisterGauge('interazap', 'php_memory_usage_bytes', 'PHP memory usage in bytes')
            ->set((float) memory_get_usage(true));
        $registry->getOrRegisterGauge('interazap', 'php_memory_peak_bytes', 'PHP peak memory usage in bytes')
            ->set((float) memory_get_peak_usage(true));
    }

    private function recordBusinessMetrics(CollectorRegistry $registry): void
    {
        $metrics = $this->getBusinessMetrics();

        $ticketGauge = $registry->getOrRegisterGauge(
            'interazap',
            'chat_tickets_total',
            'Total chat tickets by status',
            ['status']
        );
        foreach ($metrics['tickets'] as $ticket) {
            $ticketGauge->set($ticket['count'], [$ticket['status']]);
        }

        $messageGauge = $registry->getOrRegisterGauge(
            'interazap',
            'chat_messages_total',
            'Total chat messages by direction',
            ['direction']
        );
        foreach ($metrics['messages'] as $message) {
            $messageGauge->set($message['count'], [$message['direction']]);
        }

        $registry->getOrRegisterGauge('interazap', 'crm_negotiations_value_total', 'Total value of CRM negotiations')
            ->set($metrics['negotiations_value']);
        $registry->getOrRegisterGauge('interazap', 'crm_negotiations_total', 'Total CRM negotiations')
            ->set($metrics['negotiations_count']);
    }

    /**
     * @param  array<int, string>  $labelNames
     */
    private function counter(string $name, string $help, array $labelNames): Counter
    {
        $key = $this->metricKey($name, $labelNames);

        if (! isset($this->counters[$key])) {
            $this->counters[$key] = $this->registry->getOrRegisterCounter(
                '',
                $name,
                $help,
                $labelNames
            );
        }

        return $this->counters[$key];
    }

    /**
     * @param  array<int, string>  $labelNames
     */
    private function gauge(string $name, string $help, array $labelNames): Gauge
    {
        $key = $this->metricKey($name, $labelNames);

        if (! isset($this->gauges[$key])) {
            $this->gauges[$key] = $this->registry->getOrRegisterGauge(
                '',
                $name,
                $help,
                $labelNames
            );
        }

        return $this->gauges[$key];
    }

    /**
     * @param  array<int, string>  $labelNames
     * @param  array<int, float>  $buckets
     */
    private function histogram(string $name, string $help, array $labelNames, array $buckets): Histogram
    {
        $key = $this->metricKey($name, $labelNames);

        if (! isset($this->histograms[$key])) {
            $this->histograms[$key] = $this->registry->getOrRegisterHistogram(
                '',
                $name,
                $help,
                $labelNames,
                $buckets
            );
        }

        return $this->histograms[$key];
    }

    /**
     * @param  array<int, string>  $labelNames
     */
    private function metricKey(string $name, array $labelNames): string
    {
        return $name.':'.implode(',', $labelNames);
    }

    private function ensureHttpBaseline(): void
    {
        $this->counter('http_requests_total', 'Total HTTP requests', [])->incBy(0);
        $this->histogram(
            'http_request_duration_seconds',
            'HTTP request latency in seconds',
            ['method', 'path', 'status'],
            self::HISTOGRAM_BUCKETS
        );
    }

    /**
     * @return array{jobs_total: int, jobs_pending: int, jobs_failed: int}
     */
    protected function getQueueMetrics(): array
    {
        try {
            $jobsPending = Queue::size();
            $jobsFailed = DB::table('failed_jobs')->count();

            return [
                'jobs_total' => $jobsPending + $jobsFailed,
                'jobs_pending' => $jobsPending,
                'jobs_failed' => $jobsFailed,
            ];
        } catch (Throwable) {
            return [
                'jobs_total' => 0,
                'jobs_pending' => 0,
                'jobs_failed' => 0,
            ];
        }
    }

    /**
     * @return array{connections: int}
     */
    protected function getDatabaseMetrics(): array
    {
        try {
            $connections = DB::select('SELECT count(*) as count FROM pg_stat_activity WHERE datname = current_database()');

            return [
                'connections' => (int) ($connections[0]->count ?? 0),
            ];
        } catch (Throwable) {
            return [
                'connections' => 0,
            ];
        }
    }

    /**
     * @return array{connected: int, memory_used: int}
     */
    protected function getRedisMetrics(): array
    {
        try {
            /** @var \Illuminate\Cache\RedisStore $store */
            $store = Cache::store('redis')->getStore();
            /** @var \Illuminate\Redis\Connections\Connection $redis */
            $redis = $store->connection();
            $info = $redis->info();

            return [
                'connected' => 1,
                'memory_used' => (int) ($info['used_memory'] ?? 0),
            ];
        } catch (Throwable) {
            return [
                'connected' => 0,
                'memory_used' => 0,
            ];
        }
    }

    /**
     * @return array{
     *     tickets: array<string, array{status: string, count: int}>,
     *     messages: array<string, array{direction: string, count: int}>,
     *     negotiations_value: float,
     *     negotiations_count: int
     * }
     */
    protected function getBusinessMetrics(): array
    {
        try {
            $tickets = DB::table('chat_tickets')
                ->select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->get()
                ->mapWithKeys(fn ($row) => [
                    (string) $row->status => [
                        'status' => (string) $row->status,
                        'count' => (int) $row->count,
                    ],
                ])
                ->all();

            $messages = DB::table('chat_messages')
                ->select('direction', DB::raw('count(*) as count'))
                ->groupBy('direction')
                ->get()
                ->mapWithKeys(fn ($row) => [
                    (string) $row->direction => [
                        'direction' => (string) $row->direction,
                        'count' => (int) $row->count,
                    ],
                ])
                ->all();

            $negotiationsAggregate = DB::table('crm_negotiations')
                ->selectRaw('COUNT(*) as count, COALESCE(SUM(value), 0) as total_value')
                ->first();

            $negotiationsValue = (float) ($negotiationsAggregate->total_value ?? 0);
            $negotiationsCount = (int) ($negotiationsAggregate->count ?? 0);

            return [
                'tickets' => $tickets,
                'messages' => $messages,
                'negotiations_value' => $negotiationsValue,
                'negotiations_count' => $negotiationsCount,
            ];
        } catch (Throwable) {
            return [
                'tickets' => [],
                'messages' => [],
                'negotiations_value' => 0.0,
                'negotiations_count' => 0,
            ];
        }
    }
}
