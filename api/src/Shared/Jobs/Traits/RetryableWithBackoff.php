<?php

declare(strict_types=1);

namespace Shared\Jobs\Traits;

use Carbon\Carbon;

/**
 * Trait providing exponential backoff retry strategy with jitter.
 *
 * This trait implements enterprise-grade retry patterns for queue jobs,
 * including exponential backoff delays and random jitter to prevent
 * thundering herd problems.
 */
// @phpstan-ignore-next-line
trait RetryableWithBackoff
{
    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 5;

    /**
     * The number of seconds to wait before retrying the job.
     * Uses exponential backoff: 10s, 30s, 60s, 180s, 300s
     *
     * @var array<int, int>
     */
    public array $backoff = [10, 30, 60, 180, 300];

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 120;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     */
    public int $maxExceptions = 3;

    /**
     * Get the jitter percentage for backoff randomization.
     * Override this method to customize jitter.
     */
    protected function getJitterPercentage(): float
    {
        return 0.20;
    }

    /**
     * Determine the time at which the job should timeout.
     */
    public function retryUntil(): Carbon
    {
        return now()->addHours(24);
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     * Applies jitter to prevent thundering herd.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return array_map(
            fn (int $delay) => $this->applyJitter($delay),
            $this->getBackoffDelays()
        );
    }

    /**
     * Get the base backoff delays.
     *
     * @return array<int, int>
     */
    protected function getBackoffDelays(): array
    {
        return $this->backoff;
    }

    /**
     * Apply jitter to a delay value.
     * Adds ±20% randomization by default.
     */
    protected function applyJitter(int $delay): int
    {
        $jitterRange = (int) ($delay * $this->getJitterPercentage());
        $jitter = random_int(-$jitterRange, $jitterRange);

        return max(1, $delay + $jitter);
    }

    /**
     * Determine if the exception should cause the job to fail immediately.
     * Override this method to specify non-retryable exceptions.
     */
    protected function shouldFailImmediately(\Throwable $exception): bool
    {
        return $exception instanceof \InvalidArgumentException
            || $exception instanceof \DomainException;
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return [
            'retryable',
            'backoff:exponential',
        ];
    }
}
