<?php

declare(strict_types=1);

namespace Domain\Billing\Enums;

/**
 * Modo de tratamento ao atingir o limite mensal de mensagens IA.
 *
 * STOP: bloqueia novas mensagens ao atingir a cota.
 * OVERAGE: permite mensagens excedentes com cobrança adicional por mensagem.
 */
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
