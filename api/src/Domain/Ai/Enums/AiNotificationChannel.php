<?php

declare(strict_types=1);

namespace Domain\Ai\Enums;

/**
 * Canal de notificação para vendedor.
 */
enum AiNotificationChannel: string
{
    case EMAIL = 'email';
    case WHATSAPP = 'whatsapp';
    case INTERNAL = 'internal';

    /**
     * Retorna o label legível.
     */
    public function label(): string
    {
        return match ($this) {
            self::EMAIL => 'E-mail',
            self::WHATSAPP => 'WhatsApp',
            self::INTERNAL => 'Internal Notification',
        };
    }
}
