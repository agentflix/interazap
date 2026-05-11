<?php

declare(strict_types=1);

namespace Domain\Billing\Listeners;

use Domain\Billing\Events\BillingTenantGraceEvent;
use Illuminate\Support\Facades\Log;

/**
 * Listener de auditoria para tenant que entrou em período de graça.
 *
 * Registra o evento no log de aplicação para rastreabilidade.
 * Não implementa ShouldQueue pois é auditoria síncrona leve.
 */
final class BillingTenantGraceListener
{
    /**
     * Processa o evento de entrada em período de graça do tenant.
     */
    public function handle(BillingTenantGraceEvent $event): void
    {
        Log::info('billing.tenant_grace', [
            'tenant_id' => $event->tenantId,
            'invoice_id' => $event->invoiceId,
            'grace_deadline' => $event->graceDeadline,
            'days_overdue' => $event->daysOverdue,
        ]);
    }
}
