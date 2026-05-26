<?php

declare(strict_types=1);

namespace Domain\Chat\Enums;

/**
 * Tipo de provedor de comunicação suportado pela plataforma.
 */
enum ProviderType: string
{
    case UAZAPI = 'uazapi';
    case ZAPI = 'zapi';
    case META = 'meta';
    /** Canal de atendimento via webchat embarcado. */
    case WEB = 'web';
    case TELEGRAM = 'telegram';

    /** Retorna o rótulo amigável do provedor para exibição na interface. */
    public function label(): string
    {
        return match ($this) {
            self::UAZAPI => 'Uazapi',
            self::ZAPI => 'Z-API',
            self::META => 'Meta',
            self::WEB => 'Webchat',
            self::TELEGRAM => 'Telegram',
        };
    }
}
