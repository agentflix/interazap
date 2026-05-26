<?php

declare(strict_types=1);

namespace Domain\Gateway\Enums;

/**
 * Domínio/categoria de serviço do gateway NestJS.
 *
 * Cada valor corresponde a um Redis Stream diferente e a um conjunto de providers associados.
 */
enum GatewayDomain: string
{
    case AI = 'ai';
    case WHATSAPP = 'whatsapp';
    case PAYMENT = 'payment';

    /** Retorna o nome do Redis Stream correspondente a este domínio. */
    public function streamName(): string
    {
        return match ($this) {
            self::AI => 'ai.run.request',
            self::WHATSAPP => 'whatsapp.message.request',
            self::PAYMENT => 'payment.charge.request',
        };
    }
}
