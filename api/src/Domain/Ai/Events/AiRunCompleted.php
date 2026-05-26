<?php

declare(strict_types=1);

namespace Domain\Ai\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento disparado quando uma execução (Run) do Autopilot é concluída com sucesso.
 *
 * Utilizado para acionar listeners como AiConversationSummaryListener,
 * que enfileiram o resumo da conversa após cada run finalizado.
 */
final class AiRunCompleted
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  string  $tenantId  UUID do tenant.
     * @param  string  $ticketId  UUID do ticket associado ao run.
     * @param  string  $runId  UUID do run concluído.
     * @param  array<string, mixed>  $output  Saída produzida pelo agente.
     */
    public function __construct(
        public readonly string $tenantId,
        public readonly string $ticketId,
        public readonly string $runId,
        public readonly array $output = [],
    ) {}
}
