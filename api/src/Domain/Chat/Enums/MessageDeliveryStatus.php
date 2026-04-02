<?php

declare(strict_types=1);

namespace Domain\Chat\Enums;

/**
 * Message delivery status in chat.
 */
enum MessageDeliveryStatus: string
{
    case PENDING = 'pending';
    case SENT = 'sent';
    case DELIVERED = 'delivered';
    case READ = 'read';
    case PLAYED = 'played';
    case FAILED = 'failed';

    public static function fromUzapi(string $status): self
    {
        return match (strtolower($status)) {
            'pending', 'queued' => self::PENDING,
            'sent' => self::SENT,
            'delivered' => self::DELIVERED,
            'read' => self::READ,
            'played' => self::PLAYED,
            'error', 'failed' => self::FAILED,
            default => self::PENDING,
        };
    }

    public static function fromZApi(string $status): self
    {
        return match (strtoupper($status)) {
            'PENDING' => self::PENDING,
            'SENT' => self::SENT,
            'RECEIVED' => self::DELIVERED,
            'READ' => self::READ,
            'PLAYED' => self::PLAYED,
            'ERROR', 'FAILED' => self::FAILED,
            default => self::PENDING,
        };
    }
}
