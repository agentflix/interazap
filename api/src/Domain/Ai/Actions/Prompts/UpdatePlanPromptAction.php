<?php

declare(strict_types=1);

namespace Domain\Ai\Actions\Prompts;

use Domain\Ai\DTOs\PlanPromptDTO;
use Domain\Ai\Models\AiPromptPlan;
use Domain\Platform\Models\PlatformPlan;

/**
 * Action para atualização (upsert) de Plan Prompts.
 */
final class UpdatePlanPromptAction
{
    /**
     * Cria ou atualiza o prompt de um plano (upsert por plan_id).
     *
     * @param  PlatformPlan  $plan  Plano dono do prompt.
     * @param  PlanPromptDTO  $dto  Novos dados do prompt.
     * @return AiPromptPlan Prompt criado ou atualizado.
     */
    public function execute(PlatformPlan $plan, PlanPromptDTO $dto): AiPromptPlan
    {
        $data = $dto->toArray();
        $data['plan_id'] = $plan->id;

        return AiPromptPlan::query()->updateOrCreate(
            ['plan_id' => $plan->id],
            $data
        );
    }
}
