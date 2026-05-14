<?php

declare(strict_types=1);

namespace Domain\Billing\Listeners;

use Domain\Billing\Events\BillingTenantUnlockedEvent;
use Illuminate\Support\Facades\Log;

/**
 * Listener de auditoria para tenant desbloqueado após regularização.
 *
 * Registra o evento no log de aplicação para rastreabilidade.
 * Não implementa ShouldQueue pois é auditoria síncrona leve.
 */
final class BillingTenantUnlockedListener
{
    /**
     * Processa o evento de desbloqueio de tenant.
     */
    public function handle(BillingTenantUnlockedEvent $event): void
    {
        Log::info('billing.tenant_unlocked', [
            'tenant_id' => $event->tenantId,
            'unlocked_at' => $event->unlockedAt,
        ]);
    }
}
