<?php

declare(strict_types=1);

namespace Domain\Gateway\Exceptions;

/**
 * Exceção lançada quando o gateway não responde dentro do tempo limite configurado.
 *
 * Retorna HTTP 504 e carrega a duração do timeout para diagnóstico.
 */
final class GatewayTimeoutException extends GatewayException
{
    /**
     * @param  string  $message  Mensagem descritiva do timeout
     * @param  string|null  $correlationId  ID de correlação da mensagem que expirou
     * @param  int  $timeoutDuration  Duração do timeout em segundos
     */
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
