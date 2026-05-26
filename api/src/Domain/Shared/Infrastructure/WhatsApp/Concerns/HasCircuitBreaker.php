<?php

declare(strict_types=1);

namespace Domain\Shared\Infrastructure\WhatsApp\Concerns;

use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Trait para implementação de circuit breaker em adapters de WhatsApp.
 *
 * Gerencia os estados closed/open/half-open via cache Redis. Ao atingir o
 * threshold de falhas, abre o circuito por openTimeoutSeconds e, após esse
 * período, testa com estado half-open antes de fechar novamente.
 */
trait HasCircuitBreaker
{
    protected int $failureThreshold = 5;

    protected int $successThreshold = 2;

    protected int $halfOpenTimeoutSeconds = 30;

    protected int $openTimeoutSeconds = 60;

    /**
     * Executa a operação protegida pelo circuit breaker.
     *
     * Lança RuntimeException se o circuito estiver aberto e o timeout ainda não tiver expirado.
     *
     * @template T
     *
     * @param  string  $circuitKey  Chave identificadora do circuito (ex: 'uazapi.sendText').
     * @param  callable(): T  $operation  Operação a executar.
     * @return T Resultado da operação.
     *
     * @throws \RuntimeException Se o circuito estiver aberto.
     */
    protected function withCircuitBreaker(string $circuitKey, callable $operation): mixed
    {
        $state = $this->getCircuitState($circuitKey);

        if ($state === 'open') {
            if (! $this->shouldTransitionToHalfOpen($circuitKey)) {
                throw new RuntimeException("Circuit breaker aberto para: {$circuitKey}");
            }
            $this->setCircuitState($circuitKey, 'half-open');
            $state = 'half-open';
        }

        try {
            $result = $operation();
            $this->recordSuccess($circuitKey, $state);

            return $result;
        } catch (\Throwable $e) {
            $this->recordFailure($circuitKey, $state);
            throw $e;
        }
    }

    /**
     * Retorna o estado atual do circuit breaker ('closed', 'open' ou 'half-open').
     *
     * @param  string  $key  Chave identificadora do circuito.
     * @return string Estado atual do circuito.
     */
    protected function getCircuitState(string $key): string
    {
        return (string) Cache::get("circuit:{$key}:state", 'closed');
    }

    /**
     * Define o estado do circuit breaker no cache.
     *
     * @param  string  $key  Chave identificadora do circuito.
     * @param  string  $state  Novo estado ('closed', 'open' ou 'half-open').
     */
    protected function setCircuitState(string $key, string $state): void
    {
        Cache::put("circuit:{$key}:state", $state, now()->addMinutes(10));
    }

    /**
     * Verifica se o circuito aberto deve transicionar para half-open.
     *
     * @param  string  $key  Chave identificadora do circuito.
     * @return bool Verdadeiro se o timeout de abertura foi atingido.
     */
    protected function shouldTransitionToHalfOpen(string $key): bool
    {
        $openedAt = Cache::get("circuit:{$key}:opened_at");
        if (! $openedAt) {
            return true;
        }

        return now()->diffInSeconds($openedAt) >= $this->openTimeoutSeconds;
    }

    /**
     * Registra uma operação bem-sucedida, podendo fechar o circuito em half-open.
     *
     * @param  string  $key  Chave identificadora do circuito.
     * @param  string  $currentState  Estado atual do circuito antes da operação.
     */
    protected function recordSuccess(string $key, string $currentState): void
    {
        if ($currentState === 'half-open') {
            $successes = Cache::increment("circuit:{$key}:successes");
            if ($successes >= $this->successThreshold) {
                $this->setCircuitState($key, 'closed');
                Cache::forget("circuit:{$key}:failures");
                Cache::forget("circuit:{$key}:successes");
            }

            return;
        }

        Cache::forget("circuit:{$key}:failures");
    }

    /**
     * Registra uma falha, podendo abrir o circuito ao atingir o threshold.
     *
     * @param  string  $key  Chave identificadora do circuito.
     * @param  string  $currentState  Estado atual do circuito antes da operação.
     */
    protected function recordFailure(string $key, string $currentState): void
    {
        if ($currentState === 'half-open') {
            $this->setCircuitState($key, 'open');
            Cache::put("circuit:{$key}:opened_at", now(), now()->addMinutes(10));

            return;
        }

        $failures = Cache::increment("circuit:{$key}:failures");
        if ($failures >= $this->failureThreshold) {
            $this->setCircuitState($key, 'open');
            Cache::put("circuit:{$key}:opened_at", now(), now()->addMinutes(10));
        }
    }
}
