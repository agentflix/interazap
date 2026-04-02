<?php

declare(strict_types=1);

namespace Domain\Chat\Http\Resources;

use Domain\Shared\Http\Resources\BaseJsonResource;
use Illuminate\Http\Request;

/**
 * Resource for Ticket Evaluation (CSAT) serialization.
 */
final class ChatTicketEvaluationResource extends BaseJsonResource
{
    /**
     * Transform the resource into an array.
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
