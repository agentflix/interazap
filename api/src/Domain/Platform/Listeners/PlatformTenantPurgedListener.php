<?php

declare(strict_types=1);

namespace Domain\Platform\Listeners;

use Domain\Platform\Events\PlatformTenantPurgedEvent;
use Illuminate\Support\Facades\Log;

/**
 * Listener de auditoria para hard delete (purge) de tenant concluído.
 *
 * Registra o evento no log de aplicação para rastreabilidade.
 * Não implementa ShouldQueue pois é auditoria síncrona leve.
 */
final class PlatformTenantPurgedListener
{
    /**
     * Processa o evento de purge definitivo do tenant.
     */
    public function handle(PlatformTenantPurgedEvent $event): void
    {
        Log::info('platform.tenant_purged', [
            'tenant_id' => $event->tenantId,
            'purged_at' => $event->purgedAt,
        ]);
    }
}
