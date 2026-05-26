<?php

declare(strict_types=1);

namespace Domain\Ai\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento disparado quando uma execução (Run) do Autopilot falha.
 *
 * Emitido tanto por falhas de execução quanto por watchdog zombie cleanup,
 * permitindo que listeners e o gateway notifiquem o frontend sobre a falha.
 */
final class AiRunFailed
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  string  $tenantId  UUID do tenant.
     * @param  string  $ticketId  UUID do ticket associado.
     * @param  string  $runId  UUID do run que falhou.
     * @param  string  $correlationId  ID de correlação para rastreamento.
     * @param  string  $error  Mensagem de erro.
     * @param  string  $errorCode  Código de erro estruturado.
     */
    public function __construct(
        public readonly string $tenantId,
        public readonly string $ticketId,
        public readonly string $runId,
        public readonly string $correlationId,
        public readonly string $error,
        public readonly string $errorCode = 'run_failed',
    ) {}
}
