<?php

declare(strict_types=1);

namespace Domain\Gateway\DTOs;

use Domain\Gateway\Enums\GatewayDomain;
use Illuminate\Support\Str;

/**
 * DTO for gateway message.
 *
 * @readonly
 */
final readonly class GatewayMessage
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $correlationId,
        public string $timestamp,
        public GatewayDomain $domain,
        public string $action,
        public string $provider,
        public array $payload,
        public array $metadata = [],
    ) {}

    /**
     * Factory method to create a new GatewayMessage with auto-generated correlationId and timestamp.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $metadata
     */
    public static function create(
        GatewayDomain $domain,
        string $action,
        string $provider,
        array $payload,
        array $metadata = [],
        ?string $correlationId = null,
    ): self {
        return new self(
            correlationId: $correlationId ?? (string) Str::orderedUuid(),
            timestamp: now()->toIso8601String(),
            domain: $domain,
            action: $action,
            provider: $provider,
            payload: $payload,
            metadata: $metadata,
        );
    }

    /**
     * Convert the message to an array for Redis stream.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'correlationId' => $this->correlationId,
            'timestamp' => $this->timestamp,
            'domain' => $this->domain->value,
            'action' => $this->action,
            'provider' => $this->provider,
            'payload' => json_encode($this->payload, JSON_THROW_ON_ERROR),
            'metadata' => json_encode($this->metadata, JSON_THROW_ON_ERROR),
        ];
    }
}
