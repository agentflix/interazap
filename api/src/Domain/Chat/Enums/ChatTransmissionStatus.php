<?php

declare(strict_types=1);

namespace Domain\Chat\Enums;

/**
 * Status possíveis de uma lista de transmissão de mensagens em massa.
 */
enum ChatTransmissionStatus: string
{
    /** Rascunho — campanha ainda em configuração. */
    case DRAFT = 'draft';

    /** Agendada para envio em data futura. */
    case SCHEDULED = 'scheduled';

    /** Em execução, disparando mensagens ativamente. */
    case RUNNING = 'running';

    /** Processamento concluído com sucesso. */
    case COMPLETED = 'completed';

    /** Falha durante o envio. */
    case FAILED = 'failed';

    /** Cancelada manualmente antes da conclusão. */
    case CANCELLED = 'cancelled';

    /** Retorna o rótulo amigável do status. */
    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Rascunho',
            self::SCHEDULED => 'Agendada',
            self::RUNNING => 'Em execução',
            self::COMPLETED => 'Concluída',
            self::FAILED => 'Falhou',
            self::CANCELLED => 'Cancelada',
        };
    }
}
