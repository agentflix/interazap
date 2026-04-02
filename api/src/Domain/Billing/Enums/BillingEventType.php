<?php

declare(strict_types=1);

namespace Domain\Billing\Enums;

/**
 * Tipos de eventos de billing enviados pelo provedor.
 */
enum BillingEventType: string
{
    case PAYMENT_RECEIVED = 'PAYMENT_RECEIVED';
    case PAYMENT_CONFIRMED = 'PAYMENT_CONFIRMED';
    case PAYMENT_OVERDUE = 'PAYMENT_OVERDUE';
}
