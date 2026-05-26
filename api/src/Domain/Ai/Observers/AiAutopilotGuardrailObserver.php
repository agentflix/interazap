<?php

declare(strict_types=1);

namespace Domain\Ai\Observers;

use Domain\Ai\Models\AiAutopilotGuardrail;
use Illuminate\Support\Facades\Cache;

/**
 * Observer do AiAutopilotGuardrail.
 *
 * Invalida o cache de guardrails do tenant sempre que um guardrail é
 * salvo ou excluído, forçando recarregamento das regras na próxima execução.
 */
final class AiAutopilotGuardrailObserver
{
    /**
     * Invalida o cache ao salvar um guardrail.
     */
    public function saved(AiAutopilotGuardrail $guardrail): void
    {
        $this->forgetTenantGuardrailsCache((string) $guardrail->tenant_id);
    }

    /**
     * Invalida o cache ao excluir um guardrail.
     */
    public function deleted(AiAutopilotGuardrail $guardrail): void
    {
        $this->forgetTenantGuardrailsCache((string) $guardrail->tenant_id);
    }

    private function forgetTenantGuardrailsCache(string $tenantId): void
    {
        if ($tenantId === '') {
            return;
        }

        Cache::forget("autopilot:guardrails:tenant:{$tenantId}");
    }
}
