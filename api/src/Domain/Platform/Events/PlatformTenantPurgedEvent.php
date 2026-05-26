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

    /**
     * @param  string  $tenantId  UUID do tenant purgado.
     * @param  string  $purgedAt  Timestamp ISO 8601 do momento do purge.
     */
    public function __construct(
        public readonly string $tenantId,
        public readonly string $purgedAt,
    ) {}
}
