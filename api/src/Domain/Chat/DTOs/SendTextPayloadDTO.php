<?php

declare(strict_types=1);

namespace Domain\Chat\DTOs;

/**
 * DTO de payload de envio de mensagem de texto.
 *
 * @readonly
 */
final readonly class SendTextPayloadDTO
{
    /**
     * @param  string  $phone  Número de telefone de destino.
     * @param  string  $message  Conteúdo da mensagem de texto.
     * @param  string|null  $quotedMessageId  ID externo da mensagem citada.
     * @param  int  $delayMs  Atraso em milissegundos antes do envio.
     * @param  array<string, mixed>  $metadata  Metadados adicionais.
     */
    public function __construct(
        public string $phone,
        public string $message,
        public ?string $quotedMessageId = null,
        public int $delayMs = 0,
        public array $metadata = [],
    ) {}
}
