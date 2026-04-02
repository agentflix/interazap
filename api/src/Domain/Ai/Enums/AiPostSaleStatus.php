<?php

declare(strict_types=1);

namespace Domain\Ai\Enums;

/**
 * Status de agendamento de pós-venda.
 */
enum AiPostSaleStatus: string
{
    case PENDING = 'pending';
    case SENT = 'sent';
    case FAILED = 'failed';
    case SKIPPED = 'skipped';
    case CANCELLED = 'cancelled';

    /**
     * Retorna o label legível.
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::SENT => 'Sent',
            self::FAILED => 'Failed',
            self::SKIPPED => 'Skipped',
            self::CANCELLED => 'Cancelled',
        };
    }

    /**
     * Retorna a cor do badge.
     */
    public function badgeColor(): string
    {
        return match ($this) {
            self::PENDING => 'yellow',
            self::SENT => 'green',
            self::FAILED => 'red',
            self::SKIPPED => 'gray',
            self::CANCELLED => 'red',
        };
    }

    /**
     * Verifica se é um estado final.
     */
    public function isFinal(): bool
    {
        return $this !== self::PENDING;
    }
}
