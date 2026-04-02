<?php

declare(strict_types=1);

namespace Domain\Chat\Enums;

/**
 * Status possíveis de um ticket de atendimento.
 */
enum ChatTicketStatus: string
{
    /** Aguardando atribuição ou resposta inicial. */
    case PENDING = 'pending';

    /** Atendimento aberto, aguardando ação humana. */
    case OPEN = 'open';

    /** Sendo atendido ativamente por um agente humano. */
    case IN_PROGRESS = 'in_progress';

    /** Atendimento encerrado. */
    case CLOSED = 'closed';

    /** Retorna os status considerados "ativos" (não-fechados). */
    /**
     * @return array<int, self>
     */
    public static function active(): array
    {
        return [self::PENDING, self::OPEN, self::IN_PROGRESS];
    }

    /** Retorna o rótulo amigável do status. */
    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pendente',
            self::OPEN => 'Aberto',
            self::IN_PROGRESS => 'Em atendimento',
            self::CLOSED => 'Encerrado',
        };
    }
}
