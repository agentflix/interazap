<?php

declare(strict_types=1);

namespace Domain\Chat\DTOs;

use Carbon\Carbon;
use Domain\Chat\Enums\MessageDeliveryStatus;

/**
 * DTO de resposta unificada de envio de mensagem pelo provedor.
 *
 * @readonly
 */
final readonly class ProviderMessageDTO
{
    /**
     * @param  string  $providerMessageId  ID da mensagem retornado pelo provedor.
     * @param  MessageDeliveryStatus  $status  Status de entrega da mensagem.
     * @param  Carbon  $sentAt  Data/hora do envio.
     * @param  string|null  $errorCode  Código de erro, se houver falha.
     * @param  string|null  $errorMessage  Mensagem de erro detalhada.
     * @param  array<string, mixed>|null  $rawResponse  Resposta bruta do provedor.
     */
    public function __construct(
        public string $providerMessageId,
        public MessageDeliveryStatus $status,
        public Carbon $sentAt,
        public ?string $errorCode = null,
        public ?string $errorMessage = null,
        public ?array $rawResponse = null,
    ) {}

    /**
     * Cria um DTO de sucesso com o ID da mensagem retornado pelo provedor.
     *
     * @param  array<string, mixed>|null  $rawResponse
     */
    public static function success(string $providerMessageId, Carbon $sentAt, ?array $rawResponse = null): self
    {
        return new self(
            providerMessageId: $providerMessageId,
            status: MessageDeliveryStatus::SENT,
            sentAt: $sentAt,
            rawResponse: $rawResponse,
        );
    }

    /**
     * Cria um DTO de falha com código e mensagem de erro.
     *
     * @param  array<string, mixed>|null  $rawResponse
     */
    public static function failed(string $errorCode, string $errorMessage, ?array $rawResponse = null): self
    {
        return new self(
            providerMessageId: '',
            status: MessageDeliveryStatus::FAILED,
            sentAt: Carbon::now(),
            errorCode: $errorCode,
            errorMessage: $errorMessage,
            rawResponse: $rawResponse,
        );
    }

    /**
     * Verifica se a mensagem foi enviada com sucesso.
     */
    public function isSuccess(): bool
    {
        return $this->status !== MessageDeliveryStatus::FAILED;
    }
}
