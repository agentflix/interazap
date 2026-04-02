<?php

declare(strict_types=1);

namespace Domain\Chat\Enums;

/**
 * Webhook event type from chat providers.
 */
enum WebhookEventType: string
{
    case MESSAGE = 'message';
    case MESSAGE_STATUS = 'message_status';
    case CONNECTION = 'connection';
    case PRESENCE = 'presence';

    /**
     * Returns label for display.
     */
    public function label(): string
    {
        return match ($this) {
            self::MESSAGE => 'Message',
            self::MESSAGE_STATUS => 'Message Status',
            self::CONNECTION => 'Connection',
            self::PRESENCE => 'Presence',
        };
    }
}
