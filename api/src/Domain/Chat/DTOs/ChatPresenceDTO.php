<?php

declare(strict_types=1);

namespace Domain\Chat\DTOs;

use Illuminate\Http\Request;

/**
 * DTO for Chat Presence.
 *
 * @readonly
 */
final readonly class ChatPresenceDTO
{
    /**
     * @param  string  $ticketId  Ticket UUID.
     * @param  string  $presence  Presence type ('composing' for typing, 'recording' for recording).
     * @param  int|null  $delay  Simulated delay in milliseconds.
     */
    public function __construct(
        public string $ticketId,
        public string $presence,
        public ?int $delay = null,
    ) {}

    /**
     * Create DTO from request.
     */
    public static function fromRequest(Request $request, string $ticketId): self
    {
        return new self(
            ticketId: $ticketId,
            presence: $request->string('presence')->toString(),
            delay: $request->has('delay') ? (int) $request->input('delay') : null,
        );
    }

    /**
     * Create DTO from array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            ticketId: $data['ticket_id'],
            presence: $data['presence'] ?? 'composing',
            delay: isset($data['delay']) ? (int) $data['delay'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'ticket_id' => $this->ticketId,
            'presence' => $this->presence,
            'delay' => $this->delay,
        ], fn ($v) => $v !== null);
    }
}
