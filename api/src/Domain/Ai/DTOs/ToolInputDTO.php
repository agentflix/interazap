<?php

declare(strict_types=1);

namespace Domain\Ai\DTOs;

/**
 * DTO for tool execution input.
 *
 * @readonly
 */
final readonly class ToolInputDTO
{
    /**
     * @param  array<string, mixed>  $parameters
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public string $toolName,
        public array $parameters,
        public array $context = [],
    ) {}

    /**
     * Cria o DTO a partir de um array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            toolName: $data['tool_name'] ?? $data['toolName'] ?? '',
            parameters: $data['parameters'] ?? [],
            context: $data['context'] ?? [],
        );
    }

    /**
     * Converte para array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'tool_name' => $this->toolName,
            'parameters' => $this->parameters,
            'context' => $this->context,
        ];
    }
}
