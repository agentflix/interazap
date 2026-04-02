<?php

declare(strict_types=1);

namespace Domain\Configuration\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento de negócio para ticket encerrado.
 */
final class TicketClosedEvent
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  string  $tenantId  Identificador do tenant.
     * @param  string  $ticketId  Identificador do ticket.
     * @param  string|null  $assignedUserId  Agente responsável no fechamento.
     */
    public function __construct(
        public readonly string $tenantId,
        public readonly string $ticketId,
        public readonly ?string $assignedUserId,
    ) {}
}
