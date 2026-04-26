<?php

declare(strict_types=1);

namespace Domain\CRM\Enums;

/**
 * Status de Proposta Comercial.
 *
 * @category Enums
 */
enum CRMProposalStatus: string
{
    /**
     * Proposta em rascunho (não enviada).
     */
    case DRAFT = 'draft';

    /**
     * Proposta enviada ao cliente.
     */
    case SENT = 'sent';

    /**
     * Proposta aceita pelo cliente.
     */
    case ACCEPTED = 'accepted';

    /**
     * Proposta rejeitada pelo cliente.
     */
    case REJECTED = 'rejected';

    /**
     * Retorna label humanizado do status.
     */
    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Rascunho',
            self::SENT => 'Enviada',
            self::ACCEPTED => 'Aceita',
            self::REJECTED => 'Rejeitada',
        };
    }

    /**
     * Retorna cor para badge/UI.
     */
    public function color(): string
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::SENT => 'blue',
            self::ACCEPTED => 'green',
            self::REJECTED => 'red',
        };
    }
}
