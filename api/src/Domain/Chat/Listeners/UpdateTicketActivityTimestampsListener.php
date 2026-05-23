<?php

declare(strict_types=1);

namespace Domain\Chat\Listeners;

use Domain\Chat\Events\MessagePersisted;
use Domain\Chat\Models\ChatTicket;
use Illuminate\Support\Facades\Log;

/**
 * Atualiza os timestamps de atividade segmentada (cliente/atendente) no ticket.
 *
 * Escuta o evento {@see MessagePersisted} e, baseado na direção da mensagem,
 * atualiza os campos last_customer_message_at (incoming) ou last_agent_message_at
 * (outgoing), sempre atualizando last_message_at em ambos os casos.
 *
 * Esses timestamps são usados pelo job de auto-fechamento por inatividade para
 * determinar se um ticket deve ser encerrado automaticamente.
 */
final class UpdateTicketActivityTimestampsListener
{
    /**
     * Manipula o evento de mensagem persistida.
     *
     * @param  MessagePersisted  $event  Evento contendo ticketId e contexto com direction.
     */
    public function handle(MessagePersisted $event): void
    {
        $ticket = ChatTicket::query()
            ->where('tenant_id', $event->tenantId)
            ->find($event->ticketId);

        if (! $ticket instanceof ChatTicket) {
            Log::warning('[UpdateTicketActivityTimestampsListener] Ticket não encontrado', [
                'ticket_id' => $event->ticketId,
                'tenant_id' => $event->tenantId,
            ]);

            return;
        }

        $direction = $event->context['direction'] ?? null;
        $now = now();

        if ($direction === 'incoming') {
            $ticket->last_customer_message_at = $now;
        } elseif ($direction === 'outgoing') {
            $ticket->last_agent_message_at = $now;
        }

        $ticket->last_message_at = $now;
        $ticket->save();
    }
}
