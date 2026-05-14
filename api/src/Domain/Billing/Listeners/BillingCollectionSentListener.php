<?php

declare(strict_types=1);

namespace Domain\Billing\Listeners;

use Domain\Billing\Events\BillingCollectionSentEvent;
use Illuminate\Support\Facades\Log;

/**
 * Listener de auditoria para notificação de cobrança enviada.
 *
 * Registra o evento no log de aplicação para rastreabilidade.
 * Não implementa ShouldQueue pois é auditoria síncrona leve.
 */
final class BillingCollectionSentListener
{
    /**
     * Processa o evento de envio de cobrança.
     */
    public function handle(BillingCollectionSentEvent $event): void
    {
        Log::info('billing.collection_sent', [
            'tenant_id' => $event->tenantId,
            'invoice_id' => $event->invoiceId,
            'template_id' => $event->templateId,
            'channel' => $event->channel,
            'recipient' => $event->recipient,
            'status' => $event->status,
        ]);
    }
}
