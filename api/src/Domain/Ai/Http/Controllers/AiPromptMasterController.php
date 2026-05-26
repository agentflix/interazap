<?php

declare(strict_types=1);

namespace Domain\Ai\Http\Controllers;

use Domain\Ai\Actions\Prompts\AiPromptMasterActions;
use Domain\Ai\Actions\Prompts\CreateMasterPromptAction;
use Domain\Ai\Actions\Prompts\UpdateMasterPromptAction;
use Domain\Ai\DTOs\MasterPromptDTO;
use Domain\Ai\Http\Requests\StoreMasterPromptRequest;
use Domain\Ai\Http\Resources\MasterPromptResource;
use Domain\Ai\Models\AiPromptMaster;
use Domain\Shared\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Controller para endpoints de Master Prompts (SuperAdmin).
 */
final class AiPromptMasterController extends BaseController
{
    public function __construct(private readonly AiPromptMasterActions $actions) {}

    /**
     * Lista todos os master prompts.
     */
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', AiPromptMaster::class);

        $masters = $this->actions->list();

        return MasterPromptResource::collection($masters);
    }

    /**
     * Exibe um master prompt específico.
     *
     * @param  AiPromptMaster  $master  Instância do master prompt.
     * @return MasterPromptResource Dados do master prompt.
     */
    public function show(AiPromptMaster $master): MasterPromptResource
    {
        $this->authorize('view', $master);

        return new MasterPromptResource($master);
    }

    /**
     * Cria um novo master prompt.
     *
     * @param  StoreMasterPromptRequest  $request  Dados validados do prompt.
     * @param  CreateMasterPromptAction  $action  Ação de criação.
     * @return JsonResponse Master prompt criado.
     */
    public function store(
        StoreMasterPromptRequest $request,
        CreateMasterPromptAction $action
    ): JsonResponse {
        $this->authorize('create', AiPromptMaster::class);

        $dto = MasterPromptDTO::fromRequest($request);
        $master = $action->execute($dto);

        return $this->created(new MasterPromptResource($master), 'Master prompt created successfully.');
    }

    /**
     * Atualiza um master prompt.
     *
     * @param  StoreMasterPromptRequest  $request  Dados validados do prompt.
     * @param  AiPromptMaster  $master  Instância do master prompt a atualizar.
     * @param  UpdateMasterPromptAction  $action  Ação de atualização.
     * @return JsonResponse Master prompt atualizado.
     */
    public function update(
        StoreMasterPromptRequest $request,
        AiPromptMaster $master,
        UpdateMasterPromptAction $action
    ): JsonResponse {
        $this->authorize('update', $master);

        $dto = MasterPromptDTO::fromRequest($request);
        $master = $action->execute($master, $dto);

        return $this->success(new MasterPromptResource($master), 'Master prompt updated successfully.');
    }

    /**
     * Desativa um master prompt.
     *
     * @param  AiPromptMaster  $master  Instância do master prompt a desativar.
     * @return JsonResponse Confirmação de desativação.
     */
    public function destroy(AiPromptMaster $master): JsonResponse
    {
        $this->authorize('delete', $master);

        $this->actions->deactivate($master);

        return $this->success(null, 'Master prompt deactivated successfully.');
    }
}
