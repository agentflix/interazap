<?php

declare(strict_types=1);

namespace Domain\Chat\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento disparado após uma mensagem de chat ser persistida no banco de dados.
 *
 * Escutado por listeners que precisam reagir à chegada de novas mensagens,
 * como invalidação de resumo de conversa, envio de push notification e
 * despacho do roteador de conversa (IA ou auto-reply).
 */
final class MessagePersisted
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  string  $tenantId  Identificador do tenant.
     * @param  string  $ticketId  Identificador do ticket de atendimento.
     * @param  string  $body  Conteúdo textual da mensagem.
     * @param  array<string, mixed>  $context  Contexto adicional (ex.: direction, session_id).
     */
    public function __construct(
        public readonly string $tenantId,
        public readonly string $ticketId,
        public readonly string $body,
        public readonly array $context = [],
    ) {}
}
