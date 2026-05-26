<?php

declare(strict_types=1);

namespace Domain\Chat\Http\Resources;

use Domain\Shared\Http\Resources\BaseJsonResource;
use Illuminate\Http\Request;

/**
 * Resource de serialização de Transferência de Ticket.
 *
 * Transforma a entidade ChatTicketTransfer no formato da API,
 * expondo o histórico de movimentação entre atendentes.
 */
final class ChatTicketTransferResource extends BaseJsonResource
{
    /**
     * Transforma a entidade no array de resposta da API.
     *
     * @return array<string, mixed>
     */
    protected function data(Request $request): array
    {
        return [
            'id' => $this->id,
            'ticket_id' => $this->ticket_id,
            'from_user_id' => $this->from_user_id,
            'to_user_id' => $this->to_user_id,
            'reason' => $this->reason,
            'status' => $this->status,
            'transferred_at' => $this->iso($this->transferred_at),
            'created_at' => $this->iso($this->created_at),
            'updated_at' => $this->iso($this->updated_at),
        ];
    }
}
