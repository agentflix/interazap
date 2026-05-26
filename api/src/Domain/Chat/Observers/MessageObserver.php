<?php

declare(strict_types=1);

namespace Domain\Chat\Observers;

use Domain\Ai\Services\AutopilotRunSnapshotResolver;
use Domain\Chat\Models\ChatMessage;

/**
 * Observer de mensagens de chat.
 *
 * Reage ao ciclo de vida do modelo `ChatMessage` para manter cache e
 * snapshots de autopiloto sincronizados com a realidade do banco de dados.
 */
final class MessageObserver
{
    /**
     * Limpa o snapshot de autopiloto do ticket após criação de nova mensagem.
     */
    public function created(ChatMessage $message): void
    {
        AutopilotRunSnapshotResolver::forgetForTicket(
            (string) $message->tenant_id,
            (string) $message->ticket_id,
        );
    }
}
