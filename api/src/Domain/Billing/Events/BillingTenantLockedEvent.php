<?php

declare(strict_types=1);

namespace Domain\Billing\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento para tenant bloqueado por inadimplência.
 */
final class BillingTenantLockedEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly string $tenantId,
        public readonly string $reason,
        public readonly string $lockedAt,
    ) {}
}
