<?php

declare(strict_types=1);

namespace Domain\Gateway\Enums;

/**
 * Domain/category for gateway services.
 */
enum GatewayDomain: string
{
    case AI = 'ai';
    case WHATSAPP = 'whatsapp';
    case PAYMENT = 'payment';

    /**
     * Returns Redis stream name for this domain.
     */
    public function streamName(): string
    {
        return match ($this) {
            self::AI => 'ai.run.request',
            self::WHATSAPP => 'whatsapp.message.request',
            self::PAYMENT => 'payment.charge.request',
        };
    }
}
