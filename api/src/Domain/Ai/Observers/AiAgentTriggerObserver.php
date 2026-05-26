<?php

declare(strict_types=1);

namespace Domain\Ai\Observers;

use Domain\Ai\Models\AiAgentTrigger;
use Illuminate\Support\Facades\Cache;

/**
 * Mantém o cache de triggers do tenant sincronizado com o ciclo de vida dos triggers.
 *
 * Sempre que um trigger é salvo ou excluído, o cache utilizado pelo
 * DispatchAutopilotRunJob é reconstruído para refletir o estado atual.
 */
final class AiAgentTriggerObserver
{
    /**
     * Reconstrói o cache de triggers ao salvar um trigger.
     */
    public function saved(AiAgentTrigger $trigger): void
    {
        $this->rebuildTenantTriggerCache((string) $trigger->tenant_id);
    }

    /**
     * Reconstrói o cache de triggers ao excluir um trigger.
     */
    public function deleted(AiAgentTrigger $trigger): void
    {
        $this->rebuildTenantTriggerCache((string) $trigger->tenant_id);
    }

    /**
     * Retorna a chave de cache dos triggers ativos do tenant usada pelo dispatcher.
     */
    public static function cacheKey(string $tenantId): string
    {
        return sprintf('autopilot:triggers:tenant:%s', $tenantId);
    }

    private function rebuildTenantTriggerCache(string $tenantId): void
    {
        if ($tenantId === '') {
            return;
        }

        $cacheKey = self::cacheKey($tenantId);
        $triggers = AiAgentTrigger::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->with([
                'agent' => fn ($query) => $query
                    ->where('is_active', true)
                    ->with(['files' => fn ($filesQuery) => $filesQuery->orderBy('slug')]),
            ])
            ->get();

        Cache::put($cacheKey, $triggers, 3600);
    }
}
