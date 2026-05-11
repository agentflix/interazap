<?php

declare(strict_types=1);

namespace Domain\Billing\Listeners;

use Domain\Billing\Events\BillingTenantLockedEvent;
use Illuminate\Support\Facades\Log;

/**
 * Listener de auditoria para tenant bloqueado por inadimplência.
 *
 * Registra o evento no log de aplicação para rastreabilidade.
 * Não implementa ShouldQueue pois é auditoria síncrona leve.
 */
final class BillingTenantLockedListener
{
    /**
     * Processa o evento de bloqueio de tenant.
     */
    public function handle(BillingTenantLockedEvent $event): void
    {
        Log::info('billing.tenant_locked', [
            'tenant_id' => $event->tenantId,
            'reason' => $event->reason,
            'locked_at' => $event->lockedAt,
        ]);
    }
}
