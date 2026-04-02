<?php

declare(strict_types=1);

namespace Domain\Shared\Concerns;

use Carbon\Carbon;

/**
 * Trait providing standardized job configuration defaults.
 *
 * Provides consistent retry behavior, timeouts, and backoff strategies
 * for all queue jobs in the application.
 */
trait HasJobDefaults
{
    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array<int, int>
     */
    public array $backoff = [10, 60, 300];

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 120;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     */
    public int $maxExceptions = 2;

    /**
     * Determine the time at which the job should timeout.
     */
    public function retryUntil(): Carbon
    {
        return now()->addHours(6);
    }
}
