<?php

declare(strict_types=1);

namespace Domain\Ai\DTOs;

/**
 * DTO que representa o resumo gerado por IA de uma conversa de ticket.
 *
 * Utilizado nos fluxos de compactação de contexto e handoff para agentes
 * humanos, condensando o histórico de mensagens em um resumo estruturado
 * com contagem de mensagens e timestamp de geração.
 *
 * @readonly
 */
final readonly class AiConversationSummaryDTO
{
    public function __construct(
        public string $ticketId,
        public string $summary,
        public int $messageCount,
        public ?string $generatedAt = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            ticketId: (string) ($data['ticket_id'] ?? ''),
            summary: (string) ($data['summary'] ?? ''),
            messageCount: (int) ($data['message_count'] ?? 0),
            generatedAt: isset($data['generated_at']) ? (string) $data['generated_at'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'ticket_id' => $this->ticketId,
            'summary' => $this->summary,
            'message_count' => $this->messageCount,
            'generated_at' => $this->generatedAt,
        ];
    }
}
