<?php

declare(strict_types=1);

namespace Domain\Platform\Console\Traits;

use Illuminate\Support\Facades\Log;

/**
 * Trait for handling graceful shutdown in queue workers.
 *
 * Captures SIGTERM and SIGINT signals to allow the current job
 * to complete before shutting down the worker.
 */
// @phpstan-ignore-next-line trait.unused
trait GracefulShutdownTrait
{
    /**
     * Whether a shutdown signal has been received.
     */
    protected bool $shouldShutdown = false;

    /**
     * Whether the worker is currently processing a job.
     */
    protected bool $isProcessing = false;

    /**
     * Register signal handlers for graceful shutdown.
     */
    protected function registerShutdownHandlers(): void
    {
        if (! extension_loaded('pcntl')) {
            Log::warning('PCNTL extension not loaded. Graceful shutdown disabled.');

            return;
        }

        pcntl_async_signals(true);

        pcntl_signal(SIGTERM, [$this, 'handleShutdownSignal']);
        pcntl_signal(SIGINT, [$this, 'handleShutdownSignal']);
        pcntl_signal(SIGQUIT, [$this, 'handleShutdownSignal']);
    }

    /**
     * Handle shutdown signal.
     *
     * @param  int  $signal  The signal number received
     */
    public function handleShutdownSignal(int $signal): void
    {
        $signalName = match ($signal) {
            SIGTERM => 'SIGTERM',
            SIGINT => 'SIGINT',
            SIGQUIT => 'SIGQUIT',
            default => "UNKNOWN({$signal})",
        };

        Log::info("Received {$signalName} signal. Initiating graceful shutdown.", [
            'signal' => $signal,
            'signal_name' => $signalName,
            'is_processing' => $this->isProcessing,
        ]);

        $this->shouldShutdown = true;

        if (! $this->isProcessing) {
            $this->performShutdown();
        }
    }

    /**
     * Mark the start of job processing.
     */
    protected function markJobStart(): void
    {
        $this->isProcessing = true;
    }

    /**
     * Mark the end of job processing.
     * If a shutdown was requested during processing, perform it now.
     */
    protected function markJobComplete(): void
    {
        $this->isProcessing = false;

        if ($this->shouldShutdown) {
            $this->performShutdown();
        }
    }

    /**
     * Check if shutdown has been requested.
     */
    protected function shouldShutdown(): bool
    {
        return $this->shouldShutdown;
    }

    /**
     * Perform the actual shutdown.
     */
    protected function performShutdown(): void
    {
        Log::info('Worker performing graceful shutdown.');

        // Give time for logs to flush
        usleep(100000); // 100ms

        exit(0);
    }

    /**
     * Wrap job execution with graceful shutdown checks.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T|null
     */
    protected function executeWithGracefulShutdown(callable $callback): mixed
    {
        if ($this->shouldShutdown()) {
            return null;
        }

        $this->markJobStart();

        try {
            return $callback();
        } finally {
            $this->markJobComplete();
        }
    }
}
