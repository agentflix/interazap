<?php

declare(strict_types=1);

namespace Domain\Ai\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento disparado quando a IA salva uma resposta em um ticket.
 *
 * Usado pelo ChatAutopilotResponder para notificar o WebChat sobre
 * respostas geradas pela IA.
 *
 * @category Events
 */
final class AiResponseReceived
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  string  $tenantId  Identificador do tenant.
     * @param  string  $ticketId  Identificador do ticket.
     * @param  string|null  $messageId  Identificador da mensagem criada pela IA.
     * @param  array<string, mixed>  $context  Contexto adicional (session_id, source, etc).
     */
    public function __construct(
        public readonly string $tenantId,
        public readonly string $ticketId,
        public readonly ?string $messageId = null,
        public readonly array $context = [],
    ) {}
}
