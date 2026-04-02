<?php

declare(strict_types=1);

namespace Domain\Gateway\DTOs;

/**
 * DTO for gateway response.
 *
 * @readonly
 */
final readonly class GatewayResponse
{
    /**
     * @param  array<string, mixed>|null  $data
     */
    public function __construct(
        public string $correlationId,
        public string $timestamp,
        public bool $success,
        public ?array $data = null,
        public ?GatewayError $error = null,
    ) {}

    /**
     * Create a GatewayResponse from an array (typically from Redis stream).
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            correlationId: $data['correlationId'],
            timestamp: $data['timestamp'],
            success: $data['success'],
            data: $data['data'] ?? null,
            error: isset($data['error']) ? GatewayError::fromArray($data['error']) : null,
        );
    }

    /**
     * Check if the response represents a failed operation.
     */
    public function failed(): bool
    {
        return ! $this->success;
    }
}
