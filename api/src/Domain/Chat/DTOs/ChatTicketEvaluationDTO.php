<?php

declare(strict_types=1);

namespace Domain\Chat\DTOs;

use Illuminate\Http\Request;

/**
 * DTO de Avaliação de Ticket (CSAT).
 *
 * @readonly
 */
final readonly class ChatTicketEvaluationDTO
{
    /**
     * @param  string  $ticketId  UUID do ticket avaliado.
     * @param  int  $rating  Nota da avaliação (ex.: 1 a 5).
     * @param  string|null  $comment  Comentário opcional do cliente.
     */
    public function __construct(
        public string $ticketId,
        public int $rating,
        public ?string $comment = null,
    ) {}

    /**
     * Cria o DTO a partir de um Request HTTP.
     */
    public static function fromRequest(Request $request, string $ticketId): self
    {
        $comment = $request->input('comment');
        $comment = $comment === null ? null : trim((string) $comment);
        $comment = $comment === '' ? null : $comment;

        return new self(
            ticketId: $ticketId,
            rating: (int) $request->integer('rating'),
            comment: $comment,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'ticket_id' => $this->ticketId,
            'rating' => $this->rating,
            'comment' => $this->comment,
        ];
    }
}
