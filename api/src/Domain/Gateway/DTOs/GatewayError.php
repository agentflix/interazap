<?php

declare(strict_types=1);

namespace Domain\Gateway\DTOs;

/**
 * Representa um erro estruturado retornado pelo gateway ou provider externo.
 *
 * Contém código de erro, mensagem legível, indicador de tentativa novamente
 * e detalhes opcionais para depuração ou logging.
 */
final readonly class GatewayError
{
    /**
     * @param  string  $code  Código identificador do erro
     * @param  string  $message  Mensagem legível do erro
     * @param  bool  $retryable  Indica se a operação pode ser tentada novamente
     * @param  array<string, mixed>|null  $details  Detalhes adicionais para depuração
     */
    public function __construct(
        public string $code,
        public string $message,
        public bool $retryable,
        public ?array $details = null,
    ) {}

    /**
     * Cria uma instância a partir de um array de dados brutos.
     *
     * @param  array<string, mixed>  $data  Dados do erro vindos do gateway
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
