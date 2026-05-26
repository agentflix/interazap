<?php

declare(strict_types=1);

namespace Domain\CRM\Actions;

use Domain\CRM\DTOs\CRMNegotiationDTO;
use Domain\CRM\DTOs\CRMNegotiationTaskDTO;
use Domain\CRM\Models\CRMNegotiation;
use Domain\CRM\Models\CRMNegotiationTask;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Trait que delega todos os casos de uso de negociações para actions especializadas.
 *
 * Permite que a classe CRMNegotiationActions exponha uma interface unificada
 * sem concentrar a lógica diretamente, seguindo o padrão de delegação do domínio.
 */
trait DelegatesCRMNegotiationActions
{
    /**
     * Lista negociações com filtros e paginação.
     *
     * @param  array<string, mixed>  $filters  Filtros disponíveis: search, status, funnel_id, step_id, contact_id, user_id, per_page
     */
    public function list(string $tenantId, array $filters = []): LengthAwarePaginator
    {
        return app(ListCRMNegotiationsAction::class)->list($tenantId, $filters);
    }

    /** Cria uma nova negociação validando funil, etapa e contato. */
    public function create(string $tenantId, CRMNegotiationDTO $dto): CRMNegotiation
    {
        return app(CreateCRMNegotiationAction::class)->create($tenantId, $dto);
    }

    /** Atualiza dados de uma negociação existente. */
    public function update(string $tenantId, string $id, CRMNegotiationDTO $dto): CRMNegotiation
    {
        return app(UpdateCRMNegotiationAction::class)->update($tenantId, $id, $dto);
    }

    /** Remove uma negociação pelo ID. */
    public function delete(string $tenantId, string $id): void
    {
        app(UpdateCRMNegotiationAction::class)->delete($tenantId, $id);
    }

    /** Cria uma tarefa vinculada a uma negociação. */
    public function createTask(string $tenantId, CRMNegotiationTaskDTO $dto): CRMNegotiationTask
    {
        return app(UpdateCRMNegotiationAction::class)->createTask($tenantId, $dto);
    }

    /** Atualiza o status de uma tarefa de negociação. */
    public function updateTaskStatus(string $tenantId, string $negotiationId, string $taskId, string $status): CRMNegotiationTask
    {
        return app(UpdateCRMNegotiationAction::class)->updateTaskStatus($tenantId, $negotiationId, $taskId, $status);
    }

    /**
     * Lista todas as tarefas de uma negociação.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, CRMNegotiationTask>
     */
    public function listTasks(string $tenantId, string $negotiationId): \Illuminate\Database\Eloquent\Collection
    {
        return app(UpdateCRMNegotiationAction::class)->listTasks($tenantId, $negotiationId);
    }

    /** Atualiza os dados de uma tarefa de negociação. */
    public function updateTask(string $tenantId, string $negotiationId, string $taskId, CRMNegotiationTaskDTO $dto): CRMNegotiationTask
    {
        return app(UpdateCRMNegotiationAction::class)->updateTask($tenantId, $negotiationId, $taskId, $dto);
    }

    /** Alterna o status de conclusão de uma tarefa. */
    public function toggleTask(string $tenantId, string $negotiationId, string $taskId): CRMNegotiationTask
    {
        return app(UpdateCRMNegotiationAction::class)->toggleTask($tenantId, $negotiationId, $taskId);
    }

    /** Remove uma tarefa de negociação pelo ID. */
    public function deleteTask(string $tenantId, string $negotiationId, string $taskId): void
    {
        app(UpdateCRMNegotiationAction::class)->deleteTask($tenantId, $negotiationId, $taskId);
    }

    /** Retorna uma negociação pelo ID, lançando 404 se não pertencer ao tenant. */
    public function find(string $tenantId, string $id): CRMNegotiation
    {
        return app(ListCRMNegotiationsAction::class)->find($tenantId, $id);
    }

    /** Move uma negociação para outra etapa do funil recalculando posições. */
    public function move(string $tenantId, string $id, string $stepId, ?int $position = null): CRMNegotiation
    {
        return app(ChangeNegotiationStageAction::class)->move($tenantId, $id, $stepId, $position);
    }

    /**
     * @param  array<int, array{id: string, position: int}>  $items
     */
    public function reorder(string $tenantId, array $items): void
    {
        app(ChangeNegotiationStageAction::class)->reorder($tenantId, $items);
    }

    /** Reabre uma negociação previamente fechada. */
    public function reopen(string $tenantId, string $id): CRMNegotiation
    {
        return app(ChangeNegotiationStageAction::class)->reopen($tenantId, $id);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function kanban(string $tenantId, string $funnelId, array $filters = []): array
    {
        return app(ListCRMNegotiationsAction::class)->kanban($tenantId, $funnelId, $filters);
    }

    /** Atribui um responsável a uma negociação. */
    public function assign(string $tenantId, string $id, string $userId): CRMNegotiation
    {
        return app(AssignNegotiationAction::class)->assign($tenantId, $id, $userId);
    }

    /**
     * @return array<string, mixed>
     */
    public function convertToTicket(string $tenantId, string $id): array
    {
        return app(ConvertNegotiationToTicketAction::class)->convert($tenantId, $id);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<CRMNegotiation>
     */
    public function exportQuery(string $tenantId, array $filters = []): Builder
    {
        return app(ExportNegotiationsAction::class)->query($tenantId, $filters);
    }
}
