<?php

declare(strict_types=1);

namespace Domain\CRM\Enums;

/**
 * Status de Negociação no CRM.
 *
 * @category Enums
 */
enum CRMNegotiationStatus: string
{
    /**
     * Negociação em andamento (aberta).
     */
    case OPEN = 'open';

    /**
     * Negociação ganha (fechada com sucesso).
     */
    case WON = 'won';

    /**
     * Negociação perdida (fechada sem sucesso).
     */
    case LOST = 'lost';

    /**
     * Retorna label humanizado do status.
     */
    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'Aberta',
            self::WON => 'Ganha',
            self::LOST => 'Perdida',
        };
    }

    /**
     * Retorna cor para badge/UI.
     */
    public function color(): string
    {
        return match ($this) {
            self::OPEN => 'blue',
            self::WON => 'green',
            self::LOST => 'red',
        };
    }
}
