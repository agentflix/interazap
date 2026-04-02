<?php

declare(strict_types=1);

namespace Domain\Billing\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento para aviso de purge iminente.
 */
final class BillingPurgeWarningEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly string $tenantId,
        public readonly string $purgeDeadline,
    ) {}
}
