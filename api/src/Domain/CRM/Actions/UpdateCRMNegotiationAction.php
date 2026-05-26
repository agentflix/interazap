<?php

declare(strict_types=1);

namespace Domain\CRM\Actions;

use Domain\Ai\Enums\AutopilotTriggerType;
use Domain\Ai\Events\AutopilotTriggerFired;
use Domain\Configuration\Events\NegotiationLostEvent;
use Domain\Configuration\Events\NegotiationWonEvent;
use Domain\CRM\DTOs\CRMNegotiationDTO;
use Domain\CRM\DTOs\CRMNegotiationTaskDTO;
use Domain\CRM\Enums\CRMNegotiationStatus;
use Domain\CRM\Enums\CRMTaskStatus;
use Domain\CRM\Models\CRMContact;
use Domain\CRM\Models\CRMNegotiation;
use Domain\CRM\Models\CRMNegotiationFunnel;
use Domain\CRM\Models\CRMNegotiationFunnelStep;
use Domain\CRM\Models\CRMNegotiationTask;
use Domain\CRM\Services\CRMNegotiationHistoryService;
use Domain\CRM\Services\CRMNegotiationStatusService;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Casos de uso de atualização, exclusão e gerenciamento de tarefas de negociações CRM.
 *
 * Aplica transições de status e etapa com side effects (eventos de automação,
 * histórico de alterações) e gerencia o ciclo de vida das tarefas vinculadas.
 */
final class UpdateCRMNegotiationAction
{
    /** @var array<string, string> */
    private array $funnelNamesById = [];

    /** @var array<string, string> */
    private array $stepNamesById = [];

    public function __construct(
        private readonly CRMNegotiationStatusService $statusService,
        private readonly CRMNegotiationHistoryService $historyService,
        private readonly ListCRMNegotiationsAction $listAction,
    ) {}

    /**
     * Atualizar negociação e aplicar transições de status/etapa com side effects.
     */
    public function update(string $tenantId, string $id, CRMNegotiationDTO $dto): CRMNegotiation
    {
        $this->assertRelatedEntitiesIntegrity($tenantId, $dto);

        $negotiation = $this->listAction->find($tenantId, $id);
        $before = $negotiation->getAttributes();
        $previousStepId = (string) ($negotiation->crm_negotiation_funnel_step_id ?? '');
        $previousStatus = $negotiation->status instanceof \BackedEnum ? $negotiation->status->value : (string) $negotiation->status;

        $negotiation->fill($dto->toArray());
        $status = CRMNegotiationStatus::from($dto->status);
        $this->statusService->apply($negotiation, $status, $dto->crm_reason_loss_id);

        $currentStepId = (string) ($negotiation->crm_negotiation_funnel_step_id ?? '');
        if ($previousStepId !== '' && $currentStepId !== '' && $previousStepId !== $currentStepId) {
            AutopilotTriggerFired::dispatch(
                $tenantId,
                AutopilotTriggerType::NEGOTIATION_STAGE_CHANGED,
                [
                    'negotiation_id' => (string) $negotiation->id,
                    'from_step' => $previousStepId,
                    'to_step' => $currentStepId,
                    'funnel_id' => (string) ($negotiation->crm_negotiation_funnel_id ?? ''),
                    'source_type' => 'negotiation',
                ],
                (string) $negotiation->id,
            );
        }

        $currentStatus = $negotiation->status instanceof \BackedEnum ? $negotiation->status->value : (string) $negotiation->status;
        if ($previousStatus !== $currentStatus && $currentStatus === CRMNegotiationStatus::WON->value) {
            $this->statusService->dispatchOutcomeTrigger($tenantId, $negotiation, AutopilotTriggerType::NEGOTIATION_WON);
            NegotiationWonEvent::dispatch(
                $tenantId,
                (string) $negotiation->id,
                (string) $negotiation->title,
                (float) ($negotiation->amount ?? 0),
            );
        }

        if ($previousStatus !== $currentStatus && $currentStatus === CRMNegotiationStatus::LOST->value) {
            $this->statusService->dispatchOutcomeTrigger($tenantId, $negotiation, AutopilotTriggerType::NEGOTIATION_LOST);
            NegotiationLostEvent::dispatch(
                $tenantId,
                (string) $negotiation->id,
                (string) $negotiation->title,
                (float) ($negotiation->amount ?? 0),
            );
        }

        $this->recordUpdateHistoryNote($tenantId, $negotiation, $before);

        return $negotiation->load(['company', 'contact', 'funnel', 'step', 'tags', 'customFieldValues.field', 'user']);
    }

    /**
     * Excluir negociação no contexto do tenant.
     */
    public function delete(string $tenantId, string $id): void
    {
        $negotiation = $this->listAction->find($tenantId, $id);
        $negotiation->delete();
    }

    /**
     * Criar tarefa vinculada a uma negociação.
     */
    public function createTask(string $tenantId, CRMNegotiationTaskDTO $dto): CRMNegotiationTask
    {
        return CRMNegotiationTask::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenantId,
            ...$dto->toArray(),
        ]);
    }

    /**
     * Atualizar status de tarefa de negociação garantindo isolamento por tenant.
     */
    public function updateTaskStatus(string $tenantId, string $negotiationId, string $taskId, string $status): CRMNegotiationTask
    {
        $task = CRMNegotiationTask::query()
            ->where('tenant_id', $tenantId)
            ->where('crm_negotiation_id', $negotiationId)
            ->where('id', $taskId)
            ->firstOrFail();

        $task->status = CRMTaskStatus::from($status);
        $task->save();

        return $task->load(['negotiation.company', 'assignee']);
    }

    /**
     * Listar todas as tarefas de uma negociação.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, CRMNegotiationTask>
     */
    public function listTasks(string $tenantId, string $negotiationId): \Illuminate\Database\Eloquent\Collection
    {
        $this->listAction->find($tenantId, $negotiationId);

        return CRMNegotiationTask::query()
            ->where('tenant_id', $tenantId)
            ->where('crm_negotiation_id', $negotiationId)
            ->orderByDesc('due_date')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Atualizar tarefa de negociação.
     */
    public function updateTask(string $tenantId, string $negotiationId, string $taskId, CRMNegotiationTaskDTO $dto): CRMNegotiationTask
    {
        $task = CRMNegotiationTask::query()
            ->where('tenant_id', $tenantId)
            ->where('crm_negotiation_id', $negotiationId)
            ->where('id', $taskId)
            ->firstOrFail();

        $task->fill($dto->toArray());
        $task->save();

        return $task->load(['negotiation.company', 'assignee']);
    }

    /**
     * Alternar status de conclusão da tarefa.
     */
    public function toggleTask(string $tenantId, string $negotiationId, string $taskId): CRMNegotiationTask
    {
        $task = CRMNegotiationTask::query()
            ->where('tenant_id', $tenantId)
            ->where('crm_negotiation_id', $negotiationId)
            ->where('id', $taskId)
            ->firstOrFail();

        $task->is_completed = ! $task->is_completed;
        $task->completed_at = $task->is_completed ? now() : null;
        $task->status = $task->is_completed ? CRMTaskStatus::DONE : CRMTaskStatus::PENDING;
        $task->save();

        return $task->load(['negotiation.company', 'assignee']);
    }

    /**
     * Excluir tarefa de negociação.
     */
    public function deleteTask(string $tenantId, string $negotiationId, string $taskId): void
    {
        CRMNegotiationTask::query()
            ->where('tenant_id', $tenantId)
            ->where('crm_negotiation_id', $negotiationId)
            ->where('id', $taskId)
            ->firstOrFail()
            ->delete();
    }

    /**
     * @param  array<string, mixed>  $before
     */
    private function recordUpdateHistoryNote(string $tenantId, CRMNegotiation $negotiation, array $before): void
    {
        $this->preloadHistoryReferences($tenantId, $negotiation, $before);

        $tracked = [
            'title' => 'nome',
            'crm_negotiation_funnel_id' => 'funil',
            'crm_negotiation_funnel_step_id' => 'etapa',
            'crm_contact_id' => 'contato',
            'crm_company_id' => 'empresa',
            'auth_user_id' => 'responsável',
            'status' => 'status',
            'expected_close' => 'previsão de fechamento',
            'notes' => 'observações',
        ];

        $changes = [];
        foreach ($tracked as $attribute => $label) {
            $oldRaw = $before[$attribute] ?? null;
            $newRaw = $negotiation->{$attribute};

            if ($this->normalizeHistoryValue($oldRaw) === $this->normalizeHistoryValue($newRaw)) {
                continue;
            }

            $oldValue = $this->formatHistoryValue($tenantId, $attribute, $oldRaw);
            $newValue = $this->formatHistoryValue($tenantId, $attribute, $newRaw);
            $changes[] = sprintf('%s de "%s" para "%s"', $label, $oldValue, $newValue);
        }

        if ($changes === []) {
            return;
        }

        $this->historyService->record(
            $negotiation,
            sprintf('%s alterou %s.', $this->historyService->actorName(), implode('; ', $changes))
        );
    }

    /**
     * @param  array<string, mixed>  $before
     */
    private function preloadHistoryReferences(string $tenantId, CRMNegotiation $negotiation, array $before): void
    {
        $funnelIds = array_values(array_unique(array_filter([
            (string) ($before['crm_negotiation_funnel_id'] ?? ''),
            (string) ($negotiation->crm_negotiation_funnel_id ?? ''),
        ])));

        if ($funnelIds !== []) {
            $this->funnelNamesById = CRMNegotiationFunnel::query()
                ->where('tenant_id', $tenantId)
                ->whereIn('id', $funnelIds)
                ->get(['id', 'name'])
                ->keyBy('id')
                ->map(fn (CRMNegotiationFunnel $funnel): string => (string) $funnel->name)
                ->all();
        }

        $stepIds = array_values(array_unique(array_filter([
            (string) ($before['crm_negotiation_funnel_step_id'] ?? ''),
            (string) ($negotiation->crm_negotiation_funnel_step_id ?? ''),
        ])));

        if ($stepIds !== []) {
            $this->stepNamesById = CRMNegotiationFunnelStep::query()
                ->where('tenant_id', $tenantId)
                ->whereIn('id', $stepIds)
                ->get(['id', 'name'])
                ->keyBy('id')
                ->map(fn (CRMNegotiationFunnelStep $step): string => (string) $step->name)
                ->all();
        }
    }

    private function formatHistoryValue(string $tenantId, string $attribute, mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'não informado';
        }

        $normalizedValue = $this->normalizeHistoryValue($value);

        return match ($attribute) {
            'crm_negotiation_funnel_id' => $this->resolveFunnelName($tenantId, $normalizedValue),
            'crm_negotiation_funnel_step_id' => $this->resolveStepName($tenantId, $normalizedValue),
            default => $normalizedValue,
        };
    }

    private function normalizeHistoryValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if ($value instanceof \BackedEnum) {
            return (string) $value->value;
        }

        if ($value instanceof \UnitEnum) {
            return $value->name;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return (string) $value;
    }

    private function resolveFunnelName(string $tenantId, ?string $funnelId): string
    {
        if ($funnelId === null || $funnelId === '') {
            return 'não informado';
        }

        if (isset($this->funnelNamesById[$funnelId])) {
            return $this->funnelNamesById[$funnelId];
        }

        $funnel = CRMNegotiationFunnel::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $funnelId)
            ->first();

        return (string) data_get($funnel, 'name', $funnelId);
    }

    private function resolveStepName(string $tenantId, ?string $stepId): string
    {
        if ($stepId === null || $stepId === '') {
            return 'não informado';
        }

        if (isset($this->stepNamesById[$stepId])) {
            return $this->stepNamesById[$stepId];
        }

        $step = CRMNegotiationFunnelStep::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $stepId)
            ->first();

        return (string) data_get($step, 'name', $stepId);
    }

    private function assertRelatedEntitiesIntegrity(string $tenantId, CRMNegotiationDTO $dto): void
    {
        $funnelExists = CRMNegotiationFunnel::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $dto->crm_negotiation_funnel_id)
            ->exists();

        if (! $funnelExists) {
            throw ValidationException::withMessages([
                'crm_negotiation_funnel_id' => ['Funil inválido para este tenant.'],
            ]);
        }

        $stepExists = CRMNegotiationFunnelStep::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $dto->crm_negotiation_funnel_step_id)
            ->where('crm_negotiation_funnel_id', $dto->crm_negotiation_funnel_id)
            ->exists();

        if (! $stepExists) {
            throw ValidationException::withMessages([
                'crm_negotiation_funnel_step_id' => ['Etapa inválida para o funil informado.'],
            ]);
        }

        $contactExists = CRMContact::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $dto->crm_contact_id)
            ->exists();

        if (! $contactExists) {
            throw ValidationException::withMessages([
                'crm_contact_id' => ['Contato inválido para este tenant.'],
            ]);
        }
    }
}
