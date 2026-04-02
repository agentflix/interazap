<?php

declare(strict_types=1);

namespace Domain\Platform\Console\Listeners;

use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\Looping;
use Illuminate\Queue\Events\WorkerStopping;
use Illuminate\Support\Facades\Log;

/**
 * Listener for handling graceful shutdown in queue workers.
 *
 * This listener monitors queue events and ensures workers
 * can shut down gracefully when receiving termination signals.
 */
final class WorkerGracefulShutdownListener
{
    /**
     * Whether a shutdown has been requested.
     */
    protected static bool $shutdownRequested = false;

    /**
     * Whether a job is currently being processed.
     */
    protected static bool $isProcessing = false;

    /**
     * Handle the Looping event (before worker checks for new job).
     */
    public function handleLooping(Looping $event): void
    {
        // If shutdown was requested, stop accepting new jobs
        if (self::$shutdownRequested && ! self::$isProcessing) {
            Log::info('Graceful shutdown: Stopping worker loop.', [
                'connection' => $event->connectionName,
                'queue' => $event->queue,
            ]);

            // Return false to stop the worker
            app('queue.worker')->shouldQuit = true;
        }
    }

    /**
     * Handle the JobProcessing event (before job starts).
     */
    public function handleJobProcessing(JobProcessing $event): void
    {
        self::$isProcessing = true;

        Log::debug('Job processing started.', [
            'job' => $event->job->resolveName(),
            'queue' => $event->job->getQueue(),
        ]);
    }

    /**
     * Handle the JobProcessed event (after job completes).
     */
    public function handleJobProcessed(JobProcessed $event): void
    {
        self::$isProcessing = false;

        Log::debug('Job processing completed.', [
            'job' => $event->job->resolveName(),
            'queue' => $event->job->getQueue(),
        ]);

        // If shutdown was requested during job processing, stop now
        if (self::$shutdownRequested) {
            Log::info('Graceful shutdown: Job completed, stopping worker.', [
                'job' => $event->job->resolveName(),
            ]);

            app('queue.worker')->shouldQuit = true;
        }
    }

    /**
     * Handle the WorkerStopping event.
     */
    public function handleWorkerStopping(WorkerStopping $event): void
    {
        Log::info('Worker stopping.', [
            'status' => $event->status,
            'was_shutdown_requested' => self::$shutdownRequested,
        ]);
    }

    /**
     * Request a graceful shutdown.
     */
    public static function requestShutdown(): void
    {
        self::$shutdownRequested = true;

        Log::info('Graceful shutdown requested.', [
            'is_processing' => self::$isProcessing,
        ]);
    }

    /**
     * Check if a shutdown has been requested.
     */
    public static function isShutdownRequested(): bool
    {
        return self::$shutdownRequested;
    }

    /**
     * Check if a job is currently being processed.
     */
    public static function isProcessing(): bool
    {
        return self::$isProcessing;
    }

    /**
     * Reset state (for testing).
     */
    public static function reset(): void
    {
        self::$shutdownRequested = false;
        self::$isProcessing = false;
    }
}
