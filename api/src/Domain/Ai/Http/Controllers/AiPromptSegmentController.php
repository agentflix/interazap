<?php

declare(strict_types=1);

namespace Domain\Ai\Http\Controllers;

use Domain\Ai\Actions\Prompts\AiPromptSegmentActions;
use Domain\Ai\Actions\Prompts\CreateSegmentPromptAction;
use Domain\Ai\Actions\Prompts\UpdateSegmentPromptAction;
use Domain\Ai\DTOs\SegmentPromptDTO;
use Domain\Ai\Http\Requests\StoreSegmentPromptRequest;
use Domain\Ai\Http\Requests\UpdateSegmentPromptRequest;
use Domain\Ai\Http\Resources\SegmentPromptResource;
use Domain\Ai\Models\AiPromptSegment;
use Domain\Shared\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Controller para endpoints de Segment Prompts (SuperAdmin).
 */
final class AiPromptSegmentController extends BaseController
{
    public function __construct(private readonly AiPromptSegmentActions $actions) {}

    /**
     * Lista todos os segment prompts.
     */
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', AiPromptSegment::class);

        $segments = $this->actions->list();

        return SegmentPromptResource::collection($segments);
    }

    /**
     * Retorna um segment prompt específico.
     */
    public function show(AiPromptSegment $segment): SegmentPromptResource
    {
        $this->authorize('view', $segment);

        return new SegmentPromptResource($this->actions->loadWithMaster($segment));
    }

    /**
     * Cria um novo segment prompt.
     */
    public function store(
        StoreSegmentPromptRequest $request,
        CreateSegmentPromptAction $action
    ): JsonResponse {
        $this->authorize('create', AiPromptSegment::class);

        $dto = SegmentPromptDTO::fromRequest($request);
        $segment = $action->execute($dto);

        return $this->created(new SegmentPromptResource($segment->load('master')), 'Segment prompt created successfully.');
    }

    /**
     * Atualiza um segment prompt.
     */
    public function update(
        UpdateSegmentPromptRequest $request,
        AiPromptSegment $segment,
        UpdateSegmentPromptAction $action
    ): JsonResponse {
        $this->authorize('update', $segment);

        $dto = SegmentPromptDTO::fromRequest($request);
        $segment = $action->execute($segment, $dto);

        return $this->success(new SegmentPromptResource($segment->load('master')), 'Segment prompt updated successfully.');
    }

    /**
     * Desativa um segment prompt.
     */
    public function destroy(AiPromptSegment $segment): JsonResponse
    {
        $this->authorize('delete', $segment);

        // O model previne deletar GENERAL
        $this->actions->delete($segment);

        return $this->success(null, 'Segment prompt deleted successfully.');
    }
}
