<?php

declare(strict_types=1);

namespace Domain\Billing\Enums;

enum OverageMode: string
{
    case STOP = 'stop';
    case OVERAGE = 'overage';

    public function label(): string
    {
        return match ($this) {
            self::STOP => 'Pausar ao atingir limite',
            self::OVERAGE => 'Cobrar mensagens excedentes',
        };
    }
}
