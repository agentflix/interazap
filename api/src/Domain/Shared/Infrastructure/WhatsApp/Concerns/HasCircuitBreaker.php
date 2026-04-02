<?php

declare(strict_types=1);

namespace Domain\Shared\Infrastructure\WhatsApp\Concerns;

use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Trait para implementar circuit breaker em adapters.
 */
trait HasCircuitBreaker
{
    protected int $failureThreshold = 5;

    protected int $successThreshold = 2;

    protected int $halfOpenTimeoutSeconds = 30;

    protected int $openTimeoutSeconds = 60;

    /**
     * @template T
     *
     * @param  callable(): T  $operation
     * @return T
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

    protected function getCircuitState(string $key): string
    {
        return (string) Cache::get("circuit:{$key}:state", 'closed');
    }

    protected function setCircuitState(string $key, string $state): void
    {
        Cache::put("circuit:{$key}:state", $state, now()->addMinutes(10));
    }

    protected function shouldTransitionToHalfOpen(string $key): bool
    {
        $openedAt = Cache::get("circuit:{$key}:opened_at");
        if (! $openedAt) {
            return true;
        }

        return now()->diffInSeconds($openedAt) >= $this->openTimeoutSeconds;
    }

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
