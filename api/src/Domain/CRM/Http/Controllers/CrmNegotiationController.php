<?php

declare(strict_types=1);

namespace Domain\CRM\Http\Controllers;

use Domain\CRM\Actions\CRMNegotiationActions;
use Domain\CRM\Actions\ListCRMNegotiationsByStepAction;
use Domain\CRM\DTOs\CRMNegotiationDTO;
use Domain\CRM\DTOs\CRMNegotiationTaskDTO;
use Domain\CRM\Http\Requests\CRMNegotiationIndexRequest;
use Domain\CRM\Http\Requests\CRMNegotiationKanbanRequest;
use Domain\CRM\Http\Requests\CRMNegotiationKanbanStepRequest;
use Domain\CRM\Http\Requests\CRMNegotiationMoveRequest;
use Domain\CRM\Http\Requests\CRMNegotiationReorderRequest;
use Domain\CRM\Http\Requests\CRMNegotiationRequest;
use Domain\CRM\Http\Requests\CRMNegotiationStatusRequest;
use Domain\CRM\Http\Requests\CRMNegotiationTaskRequest;
use Domain\CRM\Http\Resources\CRMNegotiationResource;
use Domain\CRM\Http\Resources\CRMNegotiationTaskResource;
use Domain\CRM\Models\CRMNegotiation;
use Domain\Shared\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador para negociações (deals).
 *
 * @see CRMNegotiationActions
 */
final class CRMNegotiationController extends BaseController
{
    /**
     * @param  CRMNegotiationActions  $actions  Ação de gestão de negociações.
     * @param  ListCRMNegotiationsByStepAction  $listByStep  Ação de paginação por etapa.
     */
    public function __construct(
        private readonly CRMNegotiationActions $actions,
        private readonly ListCRMNegotiationsByStepAction $listByStep,
    ) {}

    /**
     * Listar negociações com filtros.
     *
     * @param  CRMNegotiationIndexRequest  $request  Requisição com filtros.
     * @return JsonResponse Lista paginada de negociações.
     */
    public function index(CRMNegotiationIndexRequest $request): JsonResponse
    {
        $this->authorize('viewAny', CRMNegotiation::class);

        $tenantId = $this->tenantId();
        $paginator = $this->actions->list($tenantId, $request->validated());
        $paginator->getCollection()->transform(fn ($item) => new CRMNegotiationResource($item));

        return $this->paginated($paginator, 'Negociações listadas');
    }

    /**
     * Criar nova negociação.
     *
     * @param  CRMNegotiationRequest  $request  Requisição com dados da negociação.
     * @return JsonResponse Negociação criada.
     */
    public function store(CRMNegotiationRequest $request): JsonResponse
    {
        $this->authorize('create', CRMNegotiation::class);

        $tenantId = $this->tenantId();
        $negotiation = $this->actions->create($tenantId, CRMNegotiationDTO::fromRequest($request));

        return $this->created(new CRMNegotiationResource($negotiation), 'Negociação criada');
    }

    /**
     * Obter detalhes de uma negociação.
     *
     * @param  Request  $request  Requisição atual.
     * @param  string  $id  ID da negociação.
     * @return JsonResponse Dados da negociação.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $tenantId = $this->tenantId();
        $negotiation = $this->actions->find($tenantId, $id);
        $this->authorize('view', $negotiation);

        return $this->success(new CRMNegotiationResource($negotiation), 'Negociação carregada');
    }

    /**
     * Atualizar negociação.
     *
     * @param  CRMNegotiationRequest  $request  Requisição com dados atualizados.
     * @param  string  $id  ID da negociação.
     * @return JsonResponse Negociação atualizada.
     */
    public function update(CRMNegotiationRequest $request, string $id): JsonResponse
    {
        $tenantId = $this->tenantId();
        $negotiation = $this->actions->find($tenantId, $id);
        $this->authorize('update', $negotiation);

        $negotiation = $this->actions->update($tenantId, $id, CRMNegotiationDTO::fromRequest($request));

        return $this->success(new CRMNegotiationResource($negotiation), 'Negociação atualizada');
    }

    /**
     * Excluir negociação.
     *
     * @param  Request  $request  Requisição atual.
     * @param  string  $id  ID da negociação.
     * @return JsonResponse Resposta sem conteúdo.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $tenantId = $this->tenantId();
        $negotiation = $this->actions->find($tenantId, $id);
        $this->authorize('delete', $negotiation);

        $this->actions->delete($tenantId, $id);

        return $this->noContent();
    }

    /**
     * Adicionar tarefa a uma negociação.
     *
     * @param  CRMNegotiationTaskRequest  $request  Requisição com dados da tarefa.
     * @param  string  $id  ID da negociação.
     * @return JsonResponse Tarefa criada.
     */
    public function addTask(CRMNegotiationTaskRequest $request, string $id): JsonResponse
    {
        $tenantId = $this->tenantId();
        $negotiation = $this->actions->find($tenantId, $id);
        $this->authorize('update', $negotiation);

        $task = $this->actions->createTask($tenantId, CRMNegotiationTaskDTO::fromRequest($request, $id));

        return $this->created(new CRMNegotiationTaskResource($task), 'Tarefa adicionada');
    }

    /**
     * Atualizar status de uma tarefa.
     *
     * @param  Request  $request  Requisição com novo status.
     * @param  string  $id  ID da negociação.
     * @param  string  $taskId  ID da tarefa.
     * @return JsonResponse Tarefa atualizada.
     */
    public function updateTaskStatus(Request $request, string $id, string $taskId): JsonResponse
    {
        $tenantId = $this->tenantId();
        $negotiation = $this->actions->find($tenantId, $id);
        $this->authorize('update', $negotiation);

        $status = $request->validate(['status' => 'required|string'])['status'];

        $task = $this->actions->updateTaskStatus($tenantId, $id, $taskId, $status);

        return $this->success(new CRMNegotiationTaskResource($task), 'Status da tarefa atualizado');
    }

    /**
     * Mover negociação para outra etapa do funil.
     *
     * @param  CRMNegotiationMoveRequest  $request  Requisição com nova etapa e posição.
     * @param  string  $id  ID da negociação.
     * @return JsonResponse Negociação movida.
     */
    public function move(CRMNegotiationMoveRequest $request, string $id): JsonResponse
    {
        $tenantId = $this->tenantId();
        $negotiation = $this->actions->find($tenantId, $id);
        $this->authorize('update', $negotiation);

        $negotiation = $this->actions->move(
            $tenantId,
            $id,
            $request->validated('crm_negotiation_funnel_step_id'),
            $request->validated('position')
        );

        return $this->success(new CRMNegotiationResource($negotiation), 'Negociação movida');
    }

    /**
     * Reordenar negociações dentro das etapas.
     *
     * @param  CRMNegotiationReorderRequest  $request  Requisição com nova ordem.
     * @return JsonResponse Confirmação.
     */
    public function reorder(CRMNegotiationReorderRequest $request): JsonResponse
    {
        $this->authorize('update', CRMNegotiation::class);

        $tenantId = $this->tenantId();
        $this->actions->reorder($tenantId, $request->validated('items'));

        return $this->success([], 'Negociações reordenadas');
    }

    /**
     * Marcar negociação como ganha.
     *
     * @param  Request  $request  Requisição atual.
     * @param  string  $id  ID da negociação.
     * @return JsonResponse Negociação marcada como ganha.
     */
    public function markWon(Request $request, string $id): JsonResponse
    {
        $tenantId = $this->tenantId();
        $negotiation = $this->actions->find($tenantId, $id);
        $this->authorize('update', $negotiation);

        $negotiation = $this->actions->markWon($tenantId, $id);

        return $this->success(new CRMNegotiationResource($negotiation), 'Negociação ganha');
    }

    /**
     * Marcar negociação como perdida.
     *
     * @param  CRMNegotiationStatusRequest  $request  Requisição com motivo de perda.
     * @param  string  $id  ID da negociação.
     * @return JsonResponse Negociação marcada como perdida.
     */
    public function markLost(CRMNegotiationStatusRequest $request, string $id): JsonResponse
    {
        $tenantId = $this->tenantId();
        $negotiation = $this->actions->find($tenantId, $id);
        $this->authorize('update', $negotiation);

        /** @var string|null $reasonLossId */
        $reasonLossId = $request->validated('crm_reason_loss_id');
        $negotiation = $this->actions->markLost($tenantId, $id, $reasonLossId);

        return $this->success(new CRMNegotiationResource($negotiation), 'Negociação perdida');
    }

    /**
     * Reabrir negociação fechada.
     *
     * @param  Request  $request  Requisição atual.
     * @param  string  $id  ID da negociação.
     * @return JsonResponse Negociação reaberta.
     */
    public function reopen(Request $request, string $id): JsonResponse
    {
        $tenantId = $this->tenantId();
        $negotiation = $this->actions->find($tenantId, $id);
        $this->authorize('update', $negotiation);

        $negotiation = $this->actions->reopen($tenantId, $id);

        return $this->success(new CRMNegotiationResource($negotiation), 'Negociação reaberta');
    }

    /**
     * Obter visualização Kanban do funil.
     *
     * @param  CRMNegotiationKanbanRequest  $request  Requisição com filtros.
     * @return JsonResponse Colunas do Kanban.
     */
    public function kanban(CRMNegotiationKanbanRequest $request): JsonResponse
    {
        $this->authorize('viewAny', CRMNegotiation::class);

        $tenantId = $this->tenantId();
        $validated = $request->validated();
        $columns = $this->actions->kanban(
            $tenantId,
            (string) $validated['funnel_id'],
            $validated
        );

        return $this->success($columns, 'Kanban carregado');
    }

    /**
     * Buscar próxima página de negociações de uma etapa do kanban via cursor pagination.
     *
     * @param  CRMNegotiationKanbanStepRequest  $request  Requisição com funnel_id, cursor e filtros.
     * @param  string  $stepId  ID da etapa do funil.
     * @return JsonResponse Negociações paginadas com cursor.
     */
    public function kanbanStep(CRMNegotiationKanbanStepRequest $request, string $stepId): JsonResponse
    {
        $this->authorize('viewAny', CRMNegotiation::class);

        $tenantId = $this->tenantId();
        $validated = $request->validated();

        $cursor = null;
        if (! empty($validated['cursor'])) {
            $cursor = ListCRMNegotiationsByStepAction::decodeCursor((string) $validated['cursor']);
        }

        $page = $this->listByStep->execute(
            $tenantId,
            (string) $validated['funnel_id'],
            $stepId,
            $cursor,
            (int) ($validated['per_page'] ?? 20),
            $validated,
        );

        return $this->success($page, 'Negociações da etapa carregadas');
    }
}
