<?php

declare(strict_types=1);

namespace Domain\Platform\Console\Listeners;

use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\Looping;
use Illuminate\Queue\Events\WorkerStopping;
use Illuminate\Support\Facades\Log;

/**
 * Listener para encerramento gracioso de workers de fila.
 *
 * Monitora eventos de fila e garante que os workers possam
 * finalizar o job atual antes de se encerrar ao receber sinais de terminação.
 */
final class WorkerGracefulShutdownListener
{
    /**
     * Indica se um encerramento foi solicitado.
     */
    protected static bool $shutdownRequested = false;

    /**
     * Indica se um job está sendo processado no momento.
     */
    protected static bool $isProcessing = false;

    /**
     * Trata o evento Looping (antes do worker verificar novo job).
     *
     * Interrompe o loop se encerramento foi solicitado e nenhum job está em processamento.
     *
     * @param  Looping  $event  Evento de loop do worker.
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
     * Trata o evento JobProcessing (antes do job iniciar).
     *
     * @param  JobProcessing  $event  Evento de início de processamento do job.
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
     * Trata o evento JobProcessed (após conclusão do job).
     *
     * Se encerramento foi solicitado durante o processamento, interrompe o worker agora.
     *
     * @param  JobProcessed  $event  Evento de conclusão de processamento do job.
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
     * Trata o evento WorkerStopping (worker encerrando).
     *
     * @param  WorkerStopping  $event  Evento de encerramento do worker.
     */
    public function handleWorkerStopping(WorkerStopping $event): void
    {
        Log::info('Worker stopping.', [
            'status' => $event->status,
            'was_shutdown_requested' => self::$shutdownRequested,
        ]);
    }

    /**
     * Solicita o encerramento gracioso do worker.
     */
    public static function requestShutdown(): void
    {
        self::$shutdownRequested = true;

        Log::info('Graceful shutdown requested.', [
            'is_processing' => self::$isProcessing,
        ]);
    }

    /**
     * Verifica se o encerramento gracioso foi solicitado.
     *
     * @return bool Verdadeiro se o encerramento foi solicitado.
     */
    public static function isShutdownRequested(): bool
    {
        return self::$shutdownRequested;
    }

    /**
     * Verifica se um job está sendo processado no momento.
     *
     * @return bool Verdadeiro se um job está em execução.
     */
    public static function isProcessing(): bool
    {
        return self::$isProcessing;
    }

    /**
     * Redefine o estado estático do listener (utilizado em testes).
     */
    public static function reset(): void
    {
        self::$shutdownRequested = false;
        self::$isProcessing = false;
    }
}
