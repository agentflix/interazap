<?php

declare(strict_types=1);

namespace Domain\Ai\Listeners;

use Domain\Ai\Events\AiRunCompleted;
use Domain\Ai\Jobs\AiSummarizeConversationJob;

/**
 * Listener que reage ao AiRunCompleted despachando o job de sumarização da conversa.
 *
 * Garante que cada run concluído resulte em um resumo atualizado do ticket
 * via AiSummarizeConversationJob, processado de forma assíncrona na fila.
 */
final class AiConversationSummaryListener
{
    /**
     * Despacha o job de sumarização ao receber o evento de run concluído.
     */
    public function handle(AiRunCompleted $event): void
    {
        AiSummarizeConversationJob::dispatch($event->tenantId, $event->ticketId);
    }
}
