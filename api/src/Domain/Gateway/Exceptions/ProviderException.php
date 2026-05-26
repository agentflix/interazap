<?php

declare(strict_types=1);

namespace Domain\Gateway\Exceptions;

use Domain\Gateway\DTOs\GatewayError;

/**
 * Exceção lançada quando um provider externo retorna um erro estruturado.
 *
 * Carrega o código de erro do provider, indicador de retentativa e detalhes opcionais.
 */
final class ProviderException extends GatewayException
{
    /**
     * @param  string  $message  Mensagem do erro retornada pelo provider
     * @param  string  $errorCode  Código de erro específico do provider
     * @param  bool  $retryable  Indica se a operação pode ser tentada novamente
     * @param  string|null  $correlationId  ID de correlação da mensagem que gerou o erro
     * @param  array<string, mixed>|null  $details  Detalhes adicionais do provider
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
     * Cria uma ProviderException a partir de um GatewayError estruturado.
     *
     * @param  GatewayError  $error  Erro estruturado recebido do gateway
     * @param  string|null  $correlationId  ID de correlação da mensagem original
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

    /** Verifica se esta exceção admite nova tentativa. */
    public function isRetryable(): bool
    {
        return $this->retryable;
    }
}
