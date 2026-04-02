<?php

declare(strict_types=1);

namespace Domain\Configuration\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento de negócio para ticket atribuído a um agente.
 */
final class TicketAssignedEvent
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  string  $tenantId  Identificador do tenant.
     * @param  string  $ticketId  Identificador do ticket.
     * @param  string  $userId  Agente atribuído.
     */
    public function __construct(
        public readonly string $tenantId,
        public readonly string $ticketId,
        public readonly string $userId,
    ) {}
}
