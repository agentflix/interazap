<?php

declare(strict_types=1);

namespace Domain\Chat\Enums;

/**
 * WhatsApp service provider type.
 */
enum ProviderType: string
{
    case UAZAPI = 'uazapi';
    case ZAPI = 'zapi';
    case META = 'meta';

    /**
     * Returns label for display.
     */
    public function label(): string
    {
        return match ($this) {
            self::UAZAPI => 'Uazapi',
            self::ZAPI => 'Z-API',
            self::META => 'Meta',
        };
    }
}
