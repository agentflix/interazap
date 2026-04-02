<?php

declare(strict_types=1);

namespace Domain\Ai\Enums;

/**
 * Status de validação de prompts de IA.
 *
 * Representa o ciclo de vida de validação de um prompt de tenant:
 * - PENDING: Aguardando validação pelo Guardian
 * - APPROVED: Validado e aprovado para uso
 * - REJECTED: Rejeitado pelo Guardian (conteúdo inseguro)
 * - QUARANTINE: Em quarentena aguardando aprovação manual do SuperAdmin
 */
enum AiPromptValidationStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case QUARANTINE = 'quarantine';

    /**
     * Retorna se o status permite execução do prompt.
     */
    public function allowsExecution(): bool
    {
        return $this === self::APPROVED;
    }

    /**
     * Retorna se o status requer ação do SuperAdmin.
     */
    public function requiresSuperAdminAction(): bool
    {
        return $this === self::QUARANTINE;
    }

    /**
     * Retorna a cor de badge para exibição.
     */
    public function badgeColor(): string
    {
        return match ($this) {
            self::PENDING => 'yellow',
            self::APPROVED => 'green',
            self::REJECTED => 'red',
            self::QUARANTINE => 'orange',
        };
    }

    /**
     * Retorna o label para exibição.
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending Validation',
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
            self::QUARANTINE => 'Quarantine',
        };
    }
}
