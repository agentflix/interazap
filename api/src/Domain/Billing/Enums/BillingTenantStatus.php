<?php

declare(strict_types=1);

namespace Domain\Billing\Enums;

/**
 * Status de inadimplência do tenant.
 */
enum BillingTenantStatus: string
{
    case ACTIVE = 'active';
    case GRACE = 'grace';
    case LOCKED = 'locked';
    case PENDING_PURGE = 'pending_purge';
    case PURGED = 'purged';

    /**
     * Retorna label amigável para exibição.
     */
    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Ativo',
            self::GRACE => 'Período de graça',
            self::LOCKED => 'Bloqueado',
            self::PENDING_PURGE => 'Pendente de exclusão',
            self::PURGED => 'Excluído',
        };
    }

    /**
     * Retorna cor para exibição na UI.
     */
    public function color(): string
    {
        return match ($this) {
            self::ACTIVE => 'success',
            self::GRACE => 'warning',
            self::LOCKED => 'danger',
            self::PENDING_PURGE => 'warning',
            self::PURGED => 'default',
        };
    }

    /**
     * Informa se o tenant está com acesso bloqueado.
     */
    public function isBlocked(): bool
    {
        return in_array($this, [
            self::LOCKED,
            self::PENDING_PURGE,
            self::PURGED,
        ], true);
    }
}
