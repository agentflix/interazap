<?php

declare(strict_types=1);

namespace Domain\Billing\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento para tenant com purge concluído.
 */
final class BillingTenantPurgedEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly string $tenantId,
        public readonly string $reportId,
        public readonly string $purgedAt,
    ) {}
}
