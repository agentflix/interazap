<?php

declare(strict_types=1);

namespace Domain\Billing\Enums;

/**
 * Tipos de eventos de billing enviados pelo provedor.
 *
 * Referência Asaas: https://docs.asaas.com/docs/eventos-de-cobrancas
 */
enum BillingEventType: string
{
    case PAYMENT_RECEIVED = 'PAYMENT_RECEIVED';
    case PAYMENT_CONFIRMED = 'PAYMENT_CONFIRMED';
    case PAYMENT_OVERDUE = 'PAYMENT_OVERDUE';

    // Reversão de pagamento — fatura precisa voltar de PAID para PENDING/OVERDUE.
    case PAYMENT_REFUNDED = 'PAYMENT_REFUNDED';
    case PAYMENT_CHARGEBACK_REQUESTED = 'PAYMENT_CHARGEBACK_REQUESTED';
    case PAYMENT_CHARGEBACK_DISPUTE = 'PAYMENT_CHARGEBACK_DISPUTE';
    case PAYMENT_AWAITING_CHARGEBACK_REVERSAL = 'PAYMENT_AWAITING_CHARGEBACK_REVERSAL';
    case PAYMENT_REVERSED = 'PAYMENT_REVERSED';
    case PAYMENT_DELETED = 'PAYMENT_DELETED';
}
