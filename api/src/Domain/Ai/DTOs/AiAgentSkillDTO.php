<?php

declare(strict_types=1);

namespace Domain\Ai\DTOs;

/**
 * DTO for agent skill payloads.
 *
 * @readonly
 */
final readonly class AiAgentSkillDTO
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public string $name,
        public ?string $description,
        public bool $isActive = true,
        public ?array $metadata = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: (string) ($data['name'] ?? ''),
            description: isset($data['description']) ? (string) $data['description'] : null,
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
            'name' => $this->name,
            'description' => $this->description,
            'is_active' => $this->isActive,
            'metadata' => $this->metadata,
        ];
    }
}
