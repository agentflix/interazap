<?php

declare(strict_types=1);

namespace Domain\Billing\Listeners;

use Domain\Billing\Events\BillingPurgeWarningEvent;
use Illuminate\Support\Facades\Log;

/**
 * Listener de auditoria para aviso de purge iminente.
 *
 * Registra o evento no log de aplicação para rastreabilidade.
 * Não implementa ShouldQueue pois é auditoria síncrona leve.
 */
final class BillingPurgeWarningListener
{
    /**
     * Processa o evento de aviso de purge iminente.
     */
    public function handle(BillingPurgeWarningEvent $event): void
    {
        Log::info('billing.purge_warning', [
            'tenant_id' => $event->tenantId,
            'purge_deadline' => $event->purgeDeadline,
        ]);
    }
}
