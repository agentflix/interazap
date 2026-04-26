<?php

declare(strict_types=1);

namespace Domain\CRM\Enums;

/**
 * Status de Tarefa de Negociação.
 *
 * @category Enums
 */
enum CRMTaskStatus: string
{
    /**
     * Tarefa pendente (não iniciada).
     */
    case PENDING = 'pending';

    /**
     * Tarefa em progresso.
     */
    case IN_PROGRESS = 'in_progress';

    /**
     * Tarefa concluída.
     */
    case DONE = 'done';

    /**
     * Retorna label humanizado do status.
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pendente',
            self::IN_PROGRESS => 'Em Progresso',
            self::DONE => 'Concluída',
        };
    }

    /**
     * Retorna cor para badge/UI.
     */
    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'gray',
            self::IN_PROGRESS => 'blue',
            self::DONE => 'green',
        };
    }
}
