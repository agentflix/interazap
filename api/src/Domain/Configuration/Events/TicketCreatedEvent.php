<?php

declare(strict_types=1);

namespace Domain\Configuration\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento de negócio para ticket recém-criado.
 */
final class TicketCreatedEvent
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  string  $tenantId  Identificador do tenant.
     * @param  string  $ticketId  Identificador do ticket.
     */
    public function __construct(
        public readonly string $tenantId,
        public readonly string $ticketId,
    ) {}
}
