<?php

declare(strict_types=1);

namespace Domain\Billing\Enums;

/**
 * Status possíveis para uma fatura.
 */
enum BillingInvoiceStatus: string
{
    case DRAFT = 'draft';
    case PENDING = 'pending';
    case PAID = 'paid';
    case OVERDUE = 'overdue';
    case CANCELLED = 'cancelled';

    /**
     * Retorna label amigável para exibição.
     */
    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Rascunho',
            self::PENDING => 'Pendente',
            self::PAID => 'Pago',
            self::OVERDUE => 'Atrasado',
            self::CANCELLED => 'Cancelado',
        };
    }

    /**
     * Retorna cor para exibição na UI.
     */
    public function color(): string
    {
        return match ($this) {
            self::DRAFT => 'warning',
            self::PENDING => 'primary',
            self::PAID => 'success',
            self::OVERDUE => 'danger',
            self::CANCELLED => 'default',
        };
    }
}
