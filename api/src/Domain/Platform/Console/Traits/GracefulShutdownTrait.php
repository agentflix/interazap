<?php

declare(strict_types=1);

namespace Domain\Platform\Console\Traits;

use Illuminate\Support\Facades\Log;

/**
 * Trait para encerramento gracioso em workers de fila.
 *
 * Captura os sinais SIGTERM, SIGINT e SIGQUIT para permitir que o job
 * atual seja concluído antes de encerrar o worker.
 */
// @phpstan-ignore-next-line trait.unused
trait GracefulShutdownTrait
{
    /**
     * Indica se um sinal de encerramento foi recebido.
     */
    protected bool $shouldShutdown = false;

    /**
     * Indica se o worker está processando um job no momento.
     */
    protected bool $isProcessing = false;

    /**
     * Registra os handlers de sinal para encerramento gracioso.
     *
     * Requer a extensão PCNTL. Sem ela, o encerramento gracioso é desabilitado.
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
     * Trata o sinal de encerramento recebido pelo processo.
     *
     * @param  int  $signal  Número do sinal recebido (SIGTERM, SIGINT, SIGQUIT).
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
     * Marca o início do processamento de um job.
     */
    protected function markJobStart(): void
    {
        $this->isProcessing = true;
    }

    /**
     * Marca o fim do processamento de um job.
     *
     * Se o encerramento foi solicitado durante o processamento, executa o encerramento agora.
     */
    protected function markJobComplete(): void
    {
        $this->isProcessing = false;

        if ($this->shouldShutdown) {
            $this->performShutdown();
        }
    }

    /**
     * Verifica se o encerramento foi solicitado.
     *
     * @return bool Verdadeiro se deve encerrar.
     */
    protected function shouldShutdown(): bool
    {
        return $this->shouldShutdown;
    }

    /**
     * Executa o encerramento efetivo do processo, aguardando flush dos logs.
     */
    protected function performShutdown(): void
    {
        Log::info('Worker performing graceful shutdown.');

        // Give time for logs to flush
        usleep(100000); // 100ms

        exit(0);
    }

    /**
     * Envolve a execução de um job com verificações de encerramento gracioso.
     *
     * Retorna null imediatamente se encerramento foi solicitado antes do início.
     *
     * @template T
     *
     * @param  callable(): T  $callback  Callback com a lógica do job.
     * @return T|null Resultado do callback ou null se encerramento solicitado.
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
