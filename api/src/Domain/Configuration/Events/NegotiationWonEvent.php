<?php

declare(strict_types=1);

namespace Domain\Configuration\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento de domínio para negociação ganha.
 */
final class NegotiationWonEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly string $tenantId,
        public readonly string $negotiationId,
        public readonly string $title,
        public readonly float $amount,
    ) {}
}
