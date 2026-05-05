<?php

declare(strict_types=1);

namespace Domain\Platform\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento para tenant com hard delete (purge) concluído.
 */
final class PlatformTenantPurgedEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly string $tenantId,
        public readonly string $purgedAt,
    ) {}
}
