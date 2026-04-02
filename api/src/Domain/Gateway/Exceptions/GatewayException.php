<?php

declare(strict_types=1);

namespace Domain\Gateway\Exceptions;

use Exception;

/**
 * Exception base para erros de gateway.
 */
class GatewayException extends Exception
{
    public function __construct(
        string $message,
        public readonly ?string $correlationId = null,
        int $code = 0,
        ?Exception $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
