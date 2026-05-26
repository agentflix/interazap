<?php

declare(strict_types=1);

namespace Domain\Ai\Actions\Prompts;

use Domain\Ai\Models\AiPromptPlan;
use Domain\Platform\Models\PlatformPlan;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Casos de uso de leitura para prompts de planos da plataforma.
 *
 * Contexto: consultas sobre AiPromptPlan vinculados a PlatformPlan.
 * Prompts de plano são globais à plataforma (não isolados por tenant).
 */
final class AiPromptPlanActions
{
    /**
     * Lista prompts de planos paginados com a relação plan carregada.
     *
     * @param  int  $perPage  Itens por página.
     * @return LengthAwarePaginator<int, AiPromptPlan>
     */
    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return AiPromptPlan::query()
            ->with('plan')
            ->paginate($perPage);
    }

    /**
     * Busca o prompt associado a um plano específico.
     *
     * @param  PlatformPlan  $plan  Plano a consultar.
     * @return AiPromptPlan|null Prompt do plano ou null se não configurado.
     */
    public function findByPlan(PlatformPlan $plan): ?AiPromptPlan
    {
        return AiPromptPlan::findByPlanId($plan->id);
    }
}
