<?php

declare(strict_types=1);

namespace Domain\Billing\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento para tenant que entrou em período de graça por inadimplência.
 */
final class BillingTenantGraceEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly string $tenantId,
        public readonly string $invoiceId,
        public readonly string $graceDeadline,
        public readonly int $daysOverdue,
    ) {}
}
