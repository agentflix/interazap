<?php

declare(strict_types=1);

namespace Domain\Chat\Enums;

/**
 * Tipo de mídia de uma mensagem de chat.
 */
enum MediaType: string
{
    case IMAGE = 'image';
    case VIDEO = 'video';
    case AUDIO = 'audio';
    case DOCUMENT = 'document';
    case STICKER = 'sticker';
    /** Mensagem de voz gravada (Push-to-Talk). */
    case VOICE = 'ptt';

    /** Retorna o rótulo amigável do tipo de mídia. */
    public function label(): string
    {
        return match ($this) {
            self::IMAGE => 'Imagem',
            self::VIDEO => 'Vídeo',
            self::AUDIO => 'Áudio',
            self::DOCUMENT => 'Documento',
            self::STICKER => 'Figurinha',
            self::VOICE => 'Mensagem de Voz',
        };
    }
}
