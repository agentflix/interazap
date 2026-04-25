<?php

declare(strict_types=1);

namespace Domain\Ai\Listeners;

use Domain\Ai\Events\AutopilotTriggerFired;
use Domain\Ai\Jobs\DispatchAutopilotRunJob;

/**
 * Listener para AutopilotTriggerFired — dispara job assíncrono.
 *
 * Este listener apenas valida e despacha o job DispatchAutopilotRunJob.
 * Toda a lógica pesada (DB queries, Redis, cache) executa no job,
 * isolada do processo síncrono do webhook.
 *
 * @category Listeners
 */
final class AutopilotRunDispatcherListener
{
    /**
     * Handle the AutopilotTriggerFired event.
     *
     * Valida dados mínimos e despacha o job assíncrono.
     * O job executa em fila com timeout de 300s (vs 30s do PHP max_execution_time).
     */
    public function handle(AutopilotTriggerFired $event): void
    {
        if ($event->tenantId === '' || $event->sourceId === '') {
            return;
        }

        DispatchAutopilotRunJob::dispatch(
            tenantId: $event->tenantId,
            triggerType: $event->triggerType,
            context: $event->context,
            sourceId: $event->sourceId,
        );
    }
}
