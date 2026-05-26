<?php

declare(strict_types=1);

namespace Domain\Ai\DTOs;

/**
 * DTO que representa uma regra de delegação entre agentes de IA.
 *
 * Utilizado quando um agente precisa transferir o controle da conversa para
 * outro agente especializado, definindo o alvo, profundidade máxima da cadeia
 * de delegação e metadados de contexto.
 *
 * @readonly
 */
final readonly class AiAgentDelegationDTO
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public string $targetAgentId,
        public int $maxDepth = 1,
        public bool $isActive = true,
        public ?array $metadata = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            targetAgentId: (string) ($data['target_agent_id'] ?? ''),
            maxDepth: (int) ($data['max_depth'] ?? 1),
            isActive: (bool) ($data['is_active'] ?? true),
            metadata: isset($data['metadata']) && is_array($data['metadata']) ? $data['metadata'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'target_agent_id' => $this->targetAgentId,
            'max_depth' => $this->maxDepth,
            'is_active' => $this->isActive,
            'metadata' => $this->metadata,
        ];
    }
}
