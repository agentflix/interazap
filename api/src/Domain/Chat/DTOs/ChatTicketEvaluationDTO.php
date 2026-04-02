<?php

declare(strict_types=1);

namespace Domain\Chat\DTOs;

use Illuminate\Http\Request;

/**
 * DTO for Ticket Evaluation (CSAT).
 *
 * @readonly
 */
final readonly class ChatTicketEvaluationDTO
{
    /**
     * @param  string  $ticketId  Evaluated ticket UUID.
     * @param  int  $rating  Rating value (e.g. 1 to 5).
     * @param  string|null  $comment  Optional customer comment.
     */
    public function __construct(
        public string $ticketId,
        public int $rating,
        public ?string $comment = null,
    ) {}

    /**
     * Create DTO from request.
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
