<?php

declare(strict_types=1);

namespace Domain\Ai\Observers;

use Domain\Ai\Models\AiAgent;
use Domain\Ai\Services\AutopilotRunSnapshotResolver;

/**
 * Observer do AiAgent.
 *
 * Invalida o cache de snapshot do agente no AutopilotRunSnapshotResolver
 * sempre que os dados do agente são salvos (criado ou atualizado),
 * garantindo que o próximo run carregue um snapshot atualizado.
 */
final class AiAgentObserver
{
    /**
     * Invalida o cache de snapshot ao salvar o agente.
     */
    public function saved(AiAgent $agent): void
    {
        AutopilotRunSnapshotResolver::forgetForAgent(
            (string) $agent->tenant_id,
            (string) $agent->id,
        );
    }
}
