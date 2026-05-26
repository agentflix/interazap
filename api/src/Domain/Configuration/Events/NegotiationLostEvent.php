<?php

declare(strict_types=1);

namespace Domain\Configuration\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento de domínio para negociação perdida.
 */
final class NegotiationLostEvent
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  string  $tenantId  Identificador do tenant.
     * @param  string  $negotiationId  Identificador da negociação perdida.
     * @param  string  $title  Título da negociação.
     * @param  float  $amount  Valor envolvido na negociação.
     */
    public function __construct(
        public readonly string $tenantId,
        public readonly string $negotiationId,
        public readonly string $title,
        public readonly float $amount,
    ) {}
}
