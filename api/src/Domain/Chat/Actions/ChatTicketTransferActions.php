<?php

declare(strict_types=1);

namespace Domain\Chat\Actions;

use Domain\Chat\DTOs\ChatMessageDTO;
use Domain\Chat\Models\ChatTicket;
use Domain\Chat\Models\ChatTicketTransfer;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Casos de Uso para Transferência de Tickets.
 *
 * Gerencia o histórico e a execução de transferências de atendimento
 * entre diferentes agentes.
 *
 * @category Actions
 */
final class ChatTicketTransferActions
{
    public function __construct(
        private readonly SendChatMessageAction $chatMessageActions,
    ) {}

    /**
     * Listar o histórico de transferências de um ticket específico.
     *
     * @param  string  $ticketId  UUID do ticket.
     * @return LengthAwarePaginator Paginador com registros de transferência.
     */
    public function list(string $ticketId): LengthAwarePaginator
    {
        return ChatTicketTransfer::query()
            ->where('ticket_id', $ticketId)
            ->latest()
            ->paginate();
    }

    /**
     * Executar a transferência de um ticket para um novo agente.
     *
     * Transação atômica que registra a transferência e atualiza o
     * responsável pelo ticket.
     *
     * @param  ChatTicket  $ticket  Modelo do ticket a ser transferido.
     * @param  string  $toUserId  ID do usuário que receberá o ticket.
     * @param  string  $reason  Motivo descritivo da transferência.
     * @return ChatTicketTransfer Registro da transferência criada.
     */
    public function transfer(ChatTicket $ticket, string $toUserId, string $reason): ChatTicketTransfer
    {
        return DB::transaction(function () use ($ticket, $toUserId, $reason): ChatTicketTransfer {
            $transfer = ChatTicketTransfer::query()->create([
                'tenant_id' => $ticket->tenant_id,
                'ticket_id' => $ticket->id,
                'from_user_id' => $ticket->assigned_to,
                'to_user_id' => $toUserId,
                'reason' => $reason,
                'status' => 'completed',
                'transferred_at' => now(),
            ]);

            $ticket->assigned_to = $toUserId;
            $ticket->save();

            $this->chatMessageActions->create(
                (string) $ticket->tenant_id,
                ChatMessageDTO::fromArray([
                    'ticket_id' => (string) $ticket->id,
                    'user_id' => $transfer->from_user_id,
                    'content' => $reason,
                    'type' => 'internal_note',
                    'direction' => 'outgoing',
                    'is_from_contact' => false,
                    'source' => ChatMessageDTO::SOURCE_SYSTEM,
                    'metadata' => [
                        'transfer_id' => (string) $transfer->id,
                        'from_user_id' => $transfer->from_user_id,
                        'to_user_id' => $transfer->to_user_id,
                    ],
                ]),
            );

            return $transfer;
        });
    }
}
