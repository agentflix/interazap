<?php

declare(strict_types=1);

namespace Domain\CRM\Http\Controllers;

use Domain\CRM\Actions\CRMFunnelActions;
use Domain\CRM\Http\Requests\CRMFunnelReorderRequest;
use Domain\CRM\Http\Requests\CRMFunnelRequest;
use Domain\CRM\Http\Requests\CRMFunnelStepRequest;
use Domain\CRM\Http\Requests\CRMFunnelStepUpdateRequest;
use Domain\CRM\Http\Resources\CRMFunnelResource;
use Domain\CRM\Http\Resources\CRMFunnelStepResource;
use Domain\CRM\Models\CRMNegotiationFunnel;
use Domain\Shared\Http\Controllers\BaseController;
use Domain\Shared\Support\ListFilterNormalizer;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador para funis e etapas de negociação.
 *
 * @see CRMFunnelActions
 */
final class CRMFunnelController extends BaseController
{
    /**
     * @param  CRMFunnelActions  $actions  Ação de gestão de funis.
     */
    public function __construct(private readonly CRMFunnelActions $actions) {}

    /**
     * Listar funis com filtros.
     *
     * @param  Request  $request  Requisição com filtros.
     * @return JsonResponse Lista paginada de funis.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CRMNegotiationFunnel::class);

        $tenantId = $this->tenantId();
        $isActive = ListFilterNormalizer::normalizeIsActive($request->input('is_active'));
        $paginator = $this->actions->list($tenantId, [
            'search' => $request->input('search'),
            'is_active' => $isActive,
        ]);
        $paginator->getCollection()->transform(fn ($item) => new CRMFunnelResource($item));

        return $this->paginated($paginator, 'Funis listados');
    }

    /**
     * Listar todos os funis (sem paginação).
     *
     * @param  Request  $request  Requisição atual.
     * @return JsonResponse Lista de funis.
     */
    public function all(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CRMNegotiationFunnel::class);

        $tenantId = $this->tenantId();
        $funnels = $this->actions->all($tenantId)
            ->map(fn ($item) => new CRMFunnelResource($item));

        return $this->success($funnels, 'Funis carregados');
    }

    /**
     * Criar novo funil com etapas.
     *
     * @param  CRMFunnelRequest  $request  Requisição com dados do funil.
     * @return JsonResponse Funil criado.
     */
    public function store(CRMFunnelRequest $request): JsonResponse
    {
        $this->authorize('create', CRMNegotiationFunnel::class);

        $tenantId = $this->tenantId();
        $validated = $request->validated();
        $funnel = $this->actions->create(
            $tenantId,
            $validated['name'],
            $validated['description'] ?? null,
            (bool) $validated['is_active']
        );

        if (! empty($validated['steps'])) {
            foreach ($validated['steps'] as $step) {
                $this->actions->addStep(
                    $tenantId,
                    $funnel->id,
                    $step['name'],
                    (int) $step['order'],
                    $step['color'] ?? null,
                    (bool) ($step['is_active'] ?? true)
                );
            }
        }

        return $this->created(new CRMFunnelResource($funnel->load('steps')), 'Funil criado');
    }

    /**
     * Atualizar funil.
     *
     * @param  CRMFunnelRequest  $request  Requisição com dados atualizados.
     * @param  string  $id  ID do funil.
     * @return JsonResponse Funil atualizado.
     */
    public function update(CRMFunnelRequest $request, string $id): JsonResponse
    {
        $tenantId = $this->tenantId();
        $funnel = $this->actions->find($tenantId, $id);
        $this->authorize('update', $funnel);

        $funnel = $this->actions->update($tenantId, $id, $request->validated());

        return $this->success(new CRMFunnelResource($funnel), 'Funil atualizado');
    }

    /**
     * Excluir funil.
     *
     * @param  Request  $request  Requisição atual.
     * @param  string  $id  ID do funil.
     * @return JsonResponse Resposta sem conteúdo.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $tenantId = $this->tenantId();
        $funnel = $this->actions->find($tenantId, $id);
        $this->authorize('delete', $funnel);

        try {
            $this->actions->delete($tenantId, $id);
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), '23503')) {
                return $this->error('Não é possível excluir um funil que possui negociações vinculadas.', 422);
            }

            throw $e;
        }

        return $this->noContent();
    }

    /**
     * Adicionar etapa a um funil.
     *
     * @param  CRMFunnelStepRequest  $request  Requisição com dados da etapa.
     * @param  string  $id  ID do funil.
     * @return JsonResponse Etapa criada.
     */
    public function addStep(CRMFunnelStepRequest $request, string $id): JsonResponse
    {
        $tenantId = $this->tenantId();
        $funnel = $this->actions->find($tenantId, $id);
        $this->authorize('update', $funnel);

        $validated = $request->validated();
        $step = $this->actions->addStep(
            $tenantId,
            $id,
            $validated['name'],
            (int) $validated['order'],
            $validated['color'] ?? null,
            (bool) ($validated['is_active'] ?? true)
        );

        return $this->created(new CRMFunnelStepResource($step), 'Etapa adicionada');
    }

    /**
     * Listar etapas de um funil.
     *
     * @param  Request  $request  Requisição atual.
     * @param  string  $id  ID do funil.
     * @return JsonResponse Lista de etapas.
     */
    public function steps(Request $request, string $id): JsonResponse
    {
        $tenantId = $this->tenantId();
        $funnel = $this->actions->find($tenantId, $id);
        $this->authorize('view', $funnel);

        $steps = $this->actions->listSteps($tenantId, $id)
            ->map(fn ($item) => new CRMFunnelStepResource($item));

        return $this->success(['steps' => $steps], 'Etapas carregadas');
    }

    /**
     * Atualizar etapa de um funil.
     *
     * @param  CRMFunnelStepUpdateRequest  $request  Requisição com dados atualizados.
     * @param  string  $id  ID do funil.
     * @param  string  $stepId  ID da etapa.
     * @return JsonResponse Etapa atualizada.
     */
    public function updateStep(CRMFunnelStepUpdateRequest $request, string $id, string $stepId): JsonResponse
    {
        $tenantId = $this->tenantId();
        $funnel = $this->actions->find($tenantId, $id);
        $this->authorize('update', $funnel);

        $step = $this->actions->updateStep($tenantId, $id, $stepId, $request->validated());

        return $this->success(new CRMFunnelStepResource($step), 'Etapa atualizada');
    }

    /**
     * Excluir etapa de um funil.
     *
     * @param  Request  $request  Requisição atual.
     * @param  string  $id  ID do funil.
     * @param  string  $stepId  ID da etapa.
     * @return JsonResponse Resposta sem conteúdo.
     */
    public function destroyStep(Request $request, string $id, string $stepId): JsonResponse
    {
        $tenantId = $this->tenantId();
        $funnel = $this->actions->find($tenantId, $id);
        $this->authorize('update', $funnel);

        $this->actions->deleteStep($tenantId, $id, $stepId);

        return $this->noContent();
    }

    /**
     * Reordenar etapas de um funil.
     *
     * @param  CRMFunnelReorderRequest  $request  Requisição com nova ordem.
     * @param  string  $id  ID do funil.
     * @return JsonResponse Confirmação.
     */
    public function reorder(CRMFunnelReorderRequest $request, string $id): JsonResponse
    {
        $tenantId = $this->tenantId();
        $funnel = $this->actions->find($tenantId, $id);
        $this->authorize('update', $funnel);

        $this->actions->reorder($tenantId, $id, $request->validated('steps'));

        return $this->success([], 'Etapas reordenadas');
    }
}
