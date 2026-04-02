<?php

declare(strict_types=1);

namespace Domain\Configuration\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento disparado quando uma avaliação de ticket fica abaixo da nota de corte.
 */
final class EvaluationLowScoreEvent
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  string  $tenantId  Identificador do tenant.
     * @param  string  $ticketId  Identificador do ticket avaliado.
     * @param  string  $evaluationId  Identificador da avaliação submetida.
     * @param  int  $rating  Nota enviada pelo cliente (1-5).
     */
    public function __construct(
        public readonly string $tenantId,
        public readonly string $ticketId,
        public readonly string $evaluationId,
        public readonly int $rating,
    ) {}
}
