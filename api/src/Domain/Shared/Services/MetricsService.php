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
 * Serviço responsável por registrar e renderizar métricas Prometheus.
 *
 * Combina métricas persistentes (contadores/histogramas HTTP via middleware) com
 * snapshots de negócio (tickets, mensagens, negociações) coletados sob demanda.
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
     * Renderiza todas as métricas (persistentes + snapshot) no formato Prometheus.
     *
     * @return string Texto no formato Prometheus text exposition.
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
     * Incrementa um contador dinâmico de métricas.
     *
     * @param  string  $name  Nome do contador.
     * @param  int  $value  Valor a incrementar (padrão: 1).
     * @param  array<string, string>  $labels  Labels para segmentação da métrica.
     */
    public function incrementCounter(string $name, int $value = 1, array $labels = []): void
    {
        $counter = $this->counter($name, 'Dynamic application counter', array_keys($labels));
        $counter->incBy($value, array_values($labels));
    }

    /**
     * Define o valor de um gauge dinâmico de métricas.
     *
     * @param  string  $name  Nome do gauge.
     * @param  float  $value  Valor a definir.
     * @param  array<string, string>  $labels  Labels para segmentação da métrica.
     */
    public function setGauge(string $name, float $value, array $labels = []): void
    {
        $gauge = $this->gauge($name, 'Dynamic application gauge', array_keys($labels));
        $gauge->set($value, array_values($labels));
    }

    /**
     * Registra uma observação em um histograma dinâmico de métricas.
     *
     * @param  string  $name  Nome do histograma.
     * @param  float  $value  Valor a observar.
     * @param  array<string, string>  $labels  Labels para segmentação da métrica.
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
     * Registra a duração de processamento de webhook do Autopilot como histograma.
     *
     * @param  float  $durationSeconds  Duração em segundos.
     * @param  array<string, string>  $labels  Labels para segmentação da métrica.
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
     * Incrementa o contador de bloqueios por guardrail do Autopilot.
     *
     * @param  array<string, string>  $labels  Labels para segmentação da métrica.
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
     * Define o gauge de utilização de orçamento do Autopilot (0.0 a 1.0).
     *
     * @param  float  $ratio  Taxa de utilização do orçamento.
     * @param  array<string, string>  $labels  Labels para segmentação da métrica.
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
     * Registra a duração de execução de um run do Autopilot como histograma.
     *
     * @param  float  $seconds  Duração em segundos.
     * @param  array<string, string>  $labels  Labels para segmentação da métrica.
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
     * Registra o número de iterações de ferramentas em um run do Autopilot.
     *
     * @param  int  $count  Quantidade de iterações de ferramentas no run.
     * @param  array<string, string>  $labels  Labels para segmentação da métrica.
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
     * Registra o tempo de espera por resolução de aprovação no Autopilot.
     *
     * @param  float  $seconds  Tempo de espera em segundos.
     * @param  array<string, string>  $labels  Labels para segmentação da métrica.
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
     * Registra eventos de contenção de lock de idempotência no Autopilot.
     *
     * @param  int  $count  Número de eventos de contenção (padrão: 1).
     * @param  array<string, string>  $labels  Labels para segmentação da métrica.
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

    /** Registra informações estáticas da aplicação (versão, ambiente). */
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

    /** Registra métricas de fila (total, pendentes, falhos). */
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

    /** Registra métricas de banco de dados (conexões ativas via pg_stat_activity). */
    private function recordDatabaseMetrics(CollectorRegistry $registry): void
    {
        $metrics = $this->getDatabaseMetrics();

        $registry->getOrRegisterGauge('interazap', 'database_connections_active', 'Active database connections')
            ->set($metrics['connections']);
    }

    /** Registra métricas de Redis (status de conexão e uso de memória). */
    private function recordRedisMetrics(CollectorRegistry $registry): void
    {
        $metrics = $this->getRedisMetrics();

        $registry->getOrRegisterGauge('interazap', 'redis_connected', 'Redis connection status')
            ->set($metrics['connected']);
        $registry->getOrRegisterGauge('interazap', 'redis_memory_used_bytes', 'Redis memory usage in bytes')
            ->set($metrics['memory_used']);
    }

    /** Registra métricas de sistema PHP (uso e pico de memória). */
    private function recordSystemMetrics(CollectorRegistry $registry): void
    {
        $registry->getOrRegisterGauge('interazap', 'php_memory_usage_bytes', 'PHP memory usage in bytes')
            ->set((float) memory_get_usage(true));
        $registry->getOrRegisterGauge('interazap', 'php_memory_peak_bytes', 'PHP peak memory usage in bytes')
            ->set((float) memory_get_peak_usage(true));
    }

    /** Registra métricas de negócio (tickets por status, mensagens por direção, negociações). */
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
     * Obtém ou registra um Counter no registry persistente.
     *
     * @param  string  $name  Nome da métrica.
     * @param  string  $help  Descrição da métrica.
     * @param  array<int, string>  $labelNames  Nomes dos labels.
     * @return Counter Instância do counter.
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
     * Obtém ou registra um Gauge no registry persistente.
     *
     * @param  string  $name  Nome da métrica.
     * @param  string  $help  Descrição da métrica.
     * @param  array<int, string>  $labelNames  Nomes dos labels.
     * @return Gauge Instância do gauge.
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
     * Obtém ou registra um Histogram no registry persistente.
     *
     * @param  string  $name  Nome da métrica.
     * @param  string  $help  Descrição da métrica.
     * @param  array<int, string>  $labelNames  Nomes dos labels.
     * @param  array<int, float>  $buckets  Buckets do histograma em segundos.
     * @return Histogram Instância do histograma.
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
     * Gera uma chave única para cache de instâncias de métricas.
     *
     * @param  string  $name  Nome da métrica.
     * @param  array<int, string>  $labelNames  Nomes dos labels.
     * @return string Chave composta no formato 'nome:label1,label2'.
     */
    private function metricKey(string $name, array $labelNames): string
    {
        return $name.':'.implode(',', $labelNames);
    }

    /**
     * Garante que as métricas HTTP baseline (counter e histogram) existam no registry.
     *
     * Necessário para que o endpoint /metrics sempre exiba essas métricas,
     * mesmo sem nenhuma requisição processada ainda.
     */
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
