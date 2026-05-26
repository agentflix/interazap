<?php

declare(strict_types=1);

namespace Domain\Gateway\Exceptions;

use Exception;

/**
 * Exceção base para erros de comunicação com o gateway NestJS.
 *
 * Carrega o correlationId para facilitar o rastreamento em logs.
 */
class GatewayException extends Exception
{
    /**
     * @param  string  $message  Mensagem descritiva do erro
     * @param  string|null  $correlationId  ID de correlação da mensagem que gerou o erro
     * @param  int  $code  Código HTTP ou interno do erro
     * @param  \Exception|null  $previous  Exceção anterior encadeada
     */
    public function __construct(
        string $message,
        public readonly ?string $correlationId = null,
        int $code = 0,
        ?Exception $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
