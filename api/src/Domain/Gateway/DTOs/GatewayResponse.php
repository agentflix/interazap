<?php

declare(strict_types=1);

namespace Domain\Gateway\DTOs;

/**
 * DTO imutável que representa a resposta recebida do gateway NestJS via Redis Stream.
 *
 * Carrega o correlationId para correlação, o status de sucesso, dados opcionais
 * e o erro estruturado em caso de falha.
 */
final readonly class GatewayResponse
{
    /**
     * @param  string  $correlationId  ID que correlaciona com a mensagem enviada
     * @param  string  $timestamp  Data/hora ISO-8601 da resposta
     * @param  bool  $success  Indica se a operação foi bem-sucedida
     * @param  array<string, mixed>|null  $data  Dados retornados pelo provider
     * @param  GatewayError|null  $error  Erro estruturado em caso de falha
     */
    public function __construct(
        public string $correlationId,
        public string $timestamp,
        public bool $success,
        public ?array $data = null,
        public ?GatewayError $error = null,
    ) {}

    /**
     * Cria uma instância a partir de um array, tipicamente lido do Redis Stream.
     *
     * @param  array<string, mixed>  $data  Dados brutos do stream
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

    /** Verifica se a resposta representa uma operação com falha. */
    public function failed(): bool
    {
        return ! $this->success;
    }
}
