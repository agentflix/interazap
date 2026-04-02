<?php

declare(strict_types=1);

namespace Domain\Ai\Actions\Prompts;

use Domain\Ai\Models\AiPromptPlan;
use Domain\Platform\Models\PlatformPlan;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Actions para Plan Prompts.
 */
final class AiPromptPlanActions
{
    /**
     * Listar prompts de planos com paginação.
     *
     * @return LengthAwarePaginator<int, AiPromptPlan>
     */
    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return AiPromptPlan::query()
            ->with('plan')
            ->paginate($perPage);
    }

    /**
     * Buscar prompt do plano.
     */
    public function findByPlan(PlatformPlan $plan): ?AiPromptPlan
    {
        return AiPromptPlan::findByPlanId($plan->id);
    }
}
