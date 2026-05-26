<?php

declare(strict_types=1);

namespace Domain\Chat\DTOs;

use Domain\Chat\Enums\MediaType;

/**
 * DTO de payload de envio de mídia.
 *
 * @readonly
 */
final readonly class SendMediaPayloadDTO
{
    /**
     * @param  string  $phone  Número de telefone de destino.
     * @param  MediaType  $type  Tipo de mídia a enviar.
     * @param  string  $media  URL, caminho ou base64 da mídia.
     * @param  string|null  $caption  Legenda opcional da mídia.
     * @param  string|null  $fileName  Nome do arquivo para download.
     * @param  string|null  $extension  Extensão do arquivo.
     * @param  bool  $isBase64  Indica se $media está em formato base64.
     * @param  string|null  $quotedMessageId  ID interno da mensagem citada.
     */
    public function __construct(
        public string $phone,
        public MediaType $type,
        public string $media,
        public ?string $caption = null,
        public ?string $fileName = null,
        public ?string $extension = null,
        public bool $isBase64 = false,
        public ?string $quotedMessageId = null,
    ) {}
}
