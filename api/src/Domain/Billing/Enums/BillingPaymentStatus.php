<?php

declare(strict_types=1);

namespace Domain\Billing\Enums;

/**
 * Status possíveis para um pagamento.
 */
enum BillingPaymentStatus: string
{
    case CONFIRMED = 'confirmed';
    case REFUNDED = 'refunded';

    /**
     * Retorna label amigável para exibição.
     */
    public function label(): string
    {
        return match ($this) {
            self::CONFIRMED => 'Confirmado',
            self::REFUNDED => 'Estornado',
        };
    }
}
