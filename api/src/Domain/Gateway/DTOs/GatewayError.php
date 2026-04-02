<?php

declare(strict_types=1);

namespace Domain\Gateway\DTOs;

/**
 * Represents a structured error response from a gateway provider.
 *
 * Contains error code, human-readable message, retryability flag,
 * and optional details for debugging or logging.
 *
 * @readonly
 */
final readonly class GatewayError
{
    /**
     * @param  array<string, mixed>|null  $details
     */
    public function __construct(
        public string $code,
        public string $message,
        public bool $retryable,
        public ?array $details = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            code: $data['code'],
            message: $data['message'],
            retryable: $data['retryable'] ?? false,
            details: $data['details'] ?? null,
        );
    }
}
