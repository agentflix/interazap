<?php

declare(strict_types=1);

namespace Domain\Gateway\Exceptions;

/**
 * Exception para timeouts de gateway.
 */
final class GatewayTimeoutException extends GatewayException
{
    public function __construct(
        string $message,
        ?string $correlationId = null,
        public readonly int $timeoutDuration = 0,
    ) {
        parent::__construct(
            message: $message,
            correlationId: $correlationId,
            code: 504,
        );
    }
}
