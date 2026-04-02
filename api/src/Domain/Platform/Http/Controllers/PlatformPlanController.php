<?php

declare(strict_types=1);

namespace Domain\Platform\Http\Controllers;

use Domain\Platform\Actions\CreatePlatformPlanAction;
use Domain\Platform\Actions\DeletePlatformPlanAction;
use Domain\Platform\Actions\PlatformPlanQueryActions;
use Domain\Platform\Actions\UpdatePlatformPlanAction;
use Domain\Platform\DTOs\PlatformPlanDTO;
use Domain\Platform\Http\Requests\PlatformPlanStoreRequest;
use Domain\Platform\Http\Requests\PlatformPlanUpdateRequest;
use Domain\Platform\Http\Resources\PlatformPlanResource;
use Domain\Platform\Models\PlatformPlan;
use Domain\Shared\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador de planos da plataforma.
 *
 * @see CreatePlatformPlanAction
 * @see UpdatePlatformPlanAction
 * @see DeletePlatformPlanAction
 * @see PlatformPlanQueryActions
 */
final class PlatformPlanController extends BaseController
{
    /**
     * @param  CreatePlatformPlanAction  $createAction  Ação de criação de plano.
     * @param  UpdatePlatformPlanAction  $updateAction  Ação de atualização de plano.
     * @param  DeletePlatformPlanAction  $deleteAction  Ação de exclusão de plano.
     * @param  PlatformPlanQueryActions  $queryAction  Ação de consulta de planos.
     */
    public function __construct(
        private readonly CreatePlatformPlanAction $createAction,
        private readonly UpdatePlatformPlanAction $updateAction,
        private readonly DeletePlatformPlanAction $deleteAction,
        private readonly PlatformPlanQueryActions $queryAction,
    ) {}

    /**
     * Listar planos com busca e paginação.
     *
     * @param  Request  $request  Requisição com filtros.
     * @return JsonResponse Lista paginada de planos.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PlatformPlan::class);

        $search = $request->string('search')->toString();
        $perPage = (int) $request->input('per_page', 15);

        $paginator = $this->queryAction->list($search, $perPage);
        $paginator->getCollection()->transform(fn ($item) => new PlatformPlanResource($item));

        return $this->paginated($paginator, 'Planos listados');
    }

    /**
     * Criar novo plano.
     *
     * @param  PlatformPlanStoreRequest  $request  Requisição com dados do plano.
     * @return JsonResponse Plano criado.
     */
    public function store(PlatformPlanStoreRequest $request): JsonResponse
    {
        $this->authorize('create', PlatformPlan::class);

        $plan = $this->createAction->execute(PlatformPlanDTO::fromRequest($request));

        return $this->created(new PlatformPlanResource($plan), 'Plano criado com sucesso');
    }

    /**
     * Obter detalhes de um plano.
     *
     * @param  string  $id  ID do plano.
     * @return JsonResponse Dados do plano.
     */
    public function show(string $id): JsonResponse
    {
        $plan = $this->queryAction->find($id);
        $this->authorize('view', $plan);

        return $this->success(new PlatformPlanResource($plan));
    }

    /**
     * Atualizar plano.
     *
     * @param  PlatformPlanUpdateRequest  $request  Requisição com dados atualizados.
     * @param  string  $id  ID do plano.
     * @return JsonResponse Plano atualizado.
     */
    public function update(PlatformPlanUpdateRequest $request, string $id): JsonResponse
    {
        $plan = $this->queryAction->find($id);
        $this->authorize('update', $plan);

        $updated = $this->updateAction->execute($plan, PlatformPlanDTO::fromRequest($request));

        return $this->success(new PlatformPlanResource($updated), 'Plano atualizado com sucesso');
    }

    /**
     * Excluir plano.
     *
     * @param  string  $id  ID do plano.
     * @return JsonResponse Resposta sem conteúdo ou erro de domínio.
     */
    public function destroy(string $id): JsonResponse
    {
        $plan = $this->queryAction->find($id);
        $this->authorize('delete', $plan);

        try {
            $this->deleteAction->execute($plan);
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->noContent();
    }

    /**
     * Ativar/inativar plano.
     *
     * @param  string  $id  ID do plano.
     * @return JsonResponse Plano com status atualizado.
     */
    public function toggle(string $id): JsonResponse
    {
        $plan = $this->queryAction->find($id);
        $this->authorize('update', $plan);

        $updated = $this->queryAction->toggle($id);

        return $this->success(new PlatformPlanResource($updated), 'Status atualizado');
    }

    /**
     * Validar disponibilidade de slug.
     *
     * @param  Request  $request  Requisição com slug e opcional plan_id.
     * @return JsonResponse Disponibilidade do slug.
     */
    public function validateSlug(Request $request): JsonResponse
    {
        $this->authorize('create', PlatformPlan::class);

        $slug = $request->string('slug')->toString();
        $planId = $request->string('plan_id')->toString();

        if ($slug === '') {
            return $this->error('Slug é obrigatório.', 422);
        }

        $available = $this->queryAction->validateSlug($slug, $planId !== '' ? $planId : null);

        return $this->success(['available' => $available]);
    }
}
