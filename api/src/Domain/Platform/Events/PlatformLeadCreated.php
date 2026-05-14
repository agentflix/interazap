<?php

declare(strict_types=1);

namespace Domain\Platform\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento disparado quando um novo lead da plataforma InteraZap é criado.
 *
 * Não há listeners definidos no momento — o hook fica preparado para
 * integrações futuras (notificação comercial, sincronização com CRM, etc.).
 */
final class PlatformLeadCreated
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  string  $leadId  Identificador (UUID) do lead criado.
     */
    public function __construct(
        public readonly string $leadId,
    ) {}
}
