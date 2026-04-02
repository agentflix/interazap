<?php

declare(strict_types=1);

namespace Domain\Gateway\Exceptions;

use Domain\Gateway\DTOs\GatewayError;

/**
 * Exception para erros de provider/externo.
 */
final class ProviderException extends GatewayException
{
    /**
     * @param  array<string, mixed>|null  $details
     */
    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly bool $retryable,
        ?string $correlationId = null,
        public readonly ?array $details = null,
    ) {
        parent::__construct(
            message: $message,
            correlationId: $correlationId,
        );
    }

    /**
     * Create a ProviderException from a GatewayError.
     */
    public static function fromGatewayError(GatewayError $error, ?string $correlationId = null): self
    {
        return new self(
            message: $error->message,
            errorCode: $error->code,
            retryable: $error->retryable,
            correlationId: $correlationId,
            details: $error->details,
        );
    }

    /**
     * Check if this exception is retryable.
     */
    public function isRetryable(): bool
    {
        return $this->retryable;
    }
}
