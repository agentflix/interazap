<?php

declare(strict_types=1);

namespace Domain\Chat\Http\Resources;

use Domain\Shared\Http\Resources\BaseJsonResource;
use Illuminate\Http\Request;

/**
 * Resource de serialização de Avaliação de Ticket (CSAT).
 *
 * Transforma a entidade ChatTicketEvaluation no formato da API,
 * expondo nota, comentário e data de criação.
 */
final class ChatTicketEvaluationResource extends BaseJsonResource
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
            'tenant_id' => $this->tenant_id,
            'ticket_id' => $this->ticket_id,
            'rating' => $this->rating,
            'comment' => $this->comment,
            'created_at' => $this->iso($this->created_at),
        ];
    }
}
