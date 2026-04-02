<?php

declare(strict_types=1);

namespace Domain\Ai\Http\Controllers;

use Domain\Ai\Actions\Prompts\AiPromptQuarantineActions;
use Domain\Ai\Actions\Prompts\ApproveQuarantinedPromptAction;
use Domain\Ai\Actions\Prompts\RejectQuarantinedPromptAction;
use Domain\Ai\Http\Resources\TenantPromptResource;
use Domain\Ai\Models\AiPromptTenant;
use Domain\Shared\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Controller para gerenciamento de prompts em quarentena (SuperAdmin).
 */
final class AiPromptQuarantineController extends BaseController
{
    public function __construct(private readonly AiPromptQuarantineActions $actions) {}

    /**
     * Lista todos os prompts em quarentena.
     */
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', AiPromptTenant::class);

        $quarantined = $this->actions->list();

        return TenantPromptResource::collection($quarantined);
    }

    /**
     * Retorna um prompt em quarentena específico.
     */
    public function show(string $prompt): TenantPromptResource
    {
        $promptModel = $this->actions->findById($prompt);
        $this->authorize('manageQuarantine', $promptModel);

        return new TenantPromptResource($this->actions->loadPrompt($promptModel));
    }

    /**
     * Aprova um prompt em quarentena.
     */
    public function approve(
        string $prompt,
        ApproveQuarantinedPromptAction $action
    ): JsonResponse {
        $promptModel = $this->actions->findById($prompt);
        $this->authorize('manageQuarantine', $promptModel);

        $promptModel = $action->execute($promptModel);
        $promptModel = $this->actions->loadPrompt($promptModel);

        return $this->success(new TenantPromptResource($promptModel), 'Prompt approved successfully.');
    }

    /**
     * Rejeita um prompt em quarentena.
     */
    public function reject(
        string $prompt,
        RejectQuarantinedPromptAction $action
    ): JsonResponse {
        $promptModel = $this->actions->findById($prompt);
        $this->authorize('manageQuarantine', $promptModel);

        $promptModel = $action->execute($promptModel);
        $promptModel = $this->actions->loadPrompt($promptModel);

        return $this->success(new TenantPromptResource($promptModel), 'Prompt rejected successfully.');
    }
}
