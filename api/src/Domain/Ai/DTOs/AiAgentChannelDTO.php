<?php

declare(strict_types=1);

namespace Domain\Ai\DTOs;

/**
 * DTO for agent channel payloads.
 *
 * @readonly
 */
final readonly class AiAgentChannelDTO
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public string $channel,
        public ?string $externalRef = null,
        public bool $isActive = true,
        public ?array $metadata = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            channel: (string) ($data['channel'] ?? ''),
            externalRef: isset($data['external_ref']) ? (string) $data['external_ref'] : null,
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
            'channel' => $this->channel,
            'external_ref' => $this->externalRef,
            'is_active' => $this->isActive,
            'metadata' => $this->metadata,
        ];
    }
}
