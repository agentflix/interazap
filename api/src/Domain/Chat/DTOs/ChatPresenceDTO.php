<?php

declare(strict_types=1);

namespace Domain\Chat\DTOs;

use Illuminate\Http\Request;

/**
 * DTO de Presença do Chat.
 *
 * @readonly
 */
final readonly class ChatPresenceDTO
{
    /**
     * @param  string  $ticketId  UUID do ticket.
     * @param  string  $presence  Tipo de presença ('composing' para digitando, 'recording' para gravando).
     * @param  int|null  $delay  Atraso simulado em milissegundos.
     */
    public function __construct(
        public string $ticketId,
        public string $presence,
        public ?int $delay = null,
    ) {}

    /**
     * Cria o DTO a partir de um Request HTTP.
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
     * Cria o DTO a partir de um array.
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
