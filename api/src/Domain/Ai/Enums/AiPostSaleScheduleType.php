<?php

declare(strict_types=1);

namespace Domain\Ai\Enums;

/**
 * Tipos de agendamento de pós-venda.
 */
enum AiPostSaleScheduleType: string
{
    case D1 = 'd1';
    case D7 = 'd7';
    case D30 = 'd30';

    /**
     * Retorna o offset em dias.
     */
    public function daysOffset(): int
    {
        return match ($this) {
            self::D1 => 1,
            self::D7 => 7,
            self::D30 => 30,
        };
    }

    /**
     * Retorna o label legível.
     */
    public function label(): string
    {
        return match ($this) {
            self::D1 => 'Day +1',
            self::D7 => 'Day +7',
            self::D30 => 'Day +30',
        };
    }
}
