<?php

declare(strict_types=1);

namespace Domain\Shared\Services;

use Prometheus\CollectorRegistry;
use Prometheus\Storage\InMemory;
use Prometheus\Storage\Redis;
use Throwable;

/**
 * Serviço factory que constrói um CollectorRegistry com backend Redis quando disponível.
 *
 * Tenta usar Redis como storage para persistência de métricas entre requisições.
 * Caso o Redis não esteja disponível ou a extensão não esteja carregada, faz fallback
 * para InMemory (métricas não persistidas entre requests).
 */
final class PrometheusRegistry
{
    private CollectorRegistry $registry;

    public function __construct()
    {
        if (! class_exists(\Redis::class)) {
            $this->registry = new CollectorRegistry(new InMemory);

            return;
        }

        $connection = config('database.redis.default', []);

        $options = [
            'host' => $connection['host'] ?? '127.0.0.1',
            'port' => (int) ($connection['port'] ?? 6379),
            'timeout' => 2.0,
        ];

        if (! empty($connection['password'])) {
            $options['password'] = $connection['password'];
        }

        if (isset($connection['database'])) {
            $options['database'] = (int) $connection['database'];
        }

        if ($prefix = config('database.redis.options.prefix', '')) {
            $options['prefix'] = $prefix;
        }

        try {
            $adapter = new Redis($options);
            $this->registry = new CollectorRegistry($adapter);
        } catch (Throwable) {
            $this->registry = new CollectorRegistry(new InMemory);
        }
    }

    /**
     * Retorna o CollectorRegistry configurado (Redis ou InMemory).
     *
     * @return CollectorRegistry Instância do registry Prometheus.
     */
    public function get(): CollectorRegistry
    {
        return $this->registry;
    }
}
