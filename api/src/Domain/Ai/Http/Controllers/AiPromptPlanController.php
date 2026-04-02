<?php

declare(strict_types=1);

namespace Domain\Ai\Http\Controllers;

use Domain\Ai\Actions\Prompts\AiPromptPlanActions;
use Domain\Ai\Actions\Prompts\UpdatePlanPromptAction;
use Domain\Ai\DTOs\PlanPromptDTO;
use Domain\Ai\Http\Requests\UpdatePlanPromptRequest;
use Domain\Ai\Http\Resources\PlanPromptResource;
use Domain\Ai\Models\AiPromptPlan;
use Domain\Platform\Models\PlatformPlan;
use Domain\Shared\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Controller para endpoints de Plan Prompts (SuperAdmin).
 */
final class AiPromptPlanController extends BaseController
{
    public function __construct(private readonly AiPromptPlanActions $actions) {}

    /**
     * Lista todos os plan prompts.
     */
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', AiPromptPlan::class);

        $planPrompts = $this->actions->list();

        return PlanPromptResource::collection($planPrompts);
    }

    /**
     * Retorna um plan prompt específico.
     */
    public function show(PlatformPlan $plan): JsonResponse
    {
        $this->authorize('viewAny', AiPromptPlan::class);

        $planPrompt = $this->actions->findByPlan($plan);

        if (! $planPrompt) {
            return $this->success(null, 'No prompt configured for this plan.');
        }

        return $this->success(new PlanPromptResource($planPrompt->load('plan')));
    }

    /**
     * Atualiza (ou cria) o prompt de um plano.
     */
    public function update(
        UpdatePlanPromptRequest $request,
        PlatformPlan $plan,
        UpdatePlanPromptAction $action
    ): JsonResponse {
        $this->authorize('viewAny', AiPromptPlan::class);

        $dto = PlanPromptDTO::fromRequest($request);
        $planPrompt = $action->execute($plan, $dto);

        return $this->success(new PlanPromptResource($planPrompt->load('plan')), 'Plan prompt updated successfully.');
    }
}
