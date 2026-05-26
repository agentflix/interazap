<?php

declare(strict_types=1);

namespace Domain\Chat\DTOs;

use Carbon\Carbon;
use Domain\Chat\Enums\WebhookEventType;

/**
 * DTO de evento de webhook normalizado.
 *
 * @readonly
 */
final readonly class WebhookEventDTO
{
    /**
     * @param  WebhookEventType  $eventType  Tipo do evento normalizado.
     * @param  string  $providerMessageId  ID da mensagem no provedor.
     * @param  string  $phone  Número de telefone do remetente/destinatário.
     * @param  string|null  $senderName  Nome do remetente, se disponível.
     * @param  string  $direction  Direção da mensagem (incoming/outgoing).
     * @param  string|null  $textContent  Conteúdo textual da mensagem.
     * @param  string|null  $mediaType  Tipo de mídia (image, video, audio, etc.).
     * @param  string|null  $mediaUrl  URL do arquivo de mídia.
     * @param  Carbon  $timestamp  Data/hora do evento.
     * @param  bool  $isGroup  Indica se a mensagem é de um grupo.
     * @param  string|null  $status  Status de entrega da mensagem.
     * @param  string|null  $quotedMessageId  ID da mensagem citada.
     * @param  array<string, mixed>  $rawPayload  Payload bruto original do webhook.
     */
    public function __construct(
        public WebhookEventType $eventType,
        public string $providerMessageId,
        public string $phone,
        public ?string $senderName,
        public string $direction,
        public ?string $textContent,
        public ?string $mediaType,
        public ?string $mediaUrl,
        public Carbon $timestamp,
        public bool $isGroup,
        public ?string $status,
        public ?string $quotedMessageId,
        public array $rawPayload,
    ) {}
}
