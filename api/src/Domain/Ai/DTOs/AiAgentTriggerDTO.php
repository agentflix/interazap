<?php

declare(strict_types=1);

namespace Domain\Ai\DTOs;

/**
 * DTO for agent trigger payloads.
 *
 * @readonly
 */
final readonly class AiAgentTriggerDTO
{
    /**
     * @param  array<string, mixed>|null  $config
     */
    public function __construct(
        public string $type,
        public ?array $config,
        public string $status = 'active',
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            type: (string) ($data['type'] ?? ''),
            config: isset($data['config']) && is_array($data['config']) ? $data['config'] : null,
            status: (string) ($data['status'] ?? 'active'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'config' => $this->config,
            'status' => $this->status,
        ];
    }
}
