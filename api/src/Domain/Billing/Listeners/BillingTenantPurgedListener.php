<?php

declare(strict_types=1);

namespace Domain\Billing\Listeners;

use Domain\Billing\Events\BillingTenantPurgedEvent;
use Illuminate\Support\Facades\Log;

/**
 * Listener de auditoria para purge de tenant concluído.
 *
 * Registra o evento no log de aplicação para rastreabilidade.
 * Não implementa ShouldQueue pois é auditoria síncrona leve.
 */
final class BillingTenantPurgedListener
{
    /**
     * Processa o evento de purge concluído do tenant.
     */
    public function handle(BillingTenantPurgedEvent $event): void
    {
        Log::info('billing.tenant_purged', [
            'tenant_id' => $event->tenantId,
            'report_id' => $event->reportId,
            'purged_at' => $event->purgedAt,
        ]);
    }
}
