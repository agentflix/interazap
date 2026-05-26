<?php

declare(strict_types=1);

namespace Domain\CRM\Actions;

use Domain\Ai\Enums\AutopilotTriggerType;
use Domain\Ai\Events\AutopilotTriggerFired;
use Domain\Configuration\Events\NegotiationLostEvent;
use Domain\Configuration\Events\NegotiationWonEvent;
use Domain\CRM\Enums\CRMNegotiationStatus;
use Domain\CRM\Models\CRMNegotiation;
use Domain\CRM\Models\CRMNegotiationFunnelStep;
use Domain\CRM\Services\CRMNegotiationHistoryService;
use Domain\CRM\Services\CRMNegotiationPositionService;
use Domain\CRM\Services\CRMNegotiationStatusService;
use Illuminate\Support\Facades\DB;

/**
 * Gerencia transições de etapa e status de negociações no funil de vendas.
 *
 * Centraliza as operações de mover para outra etapa, marcar como ganha,
 * marcar como perdida e reabrir, garantindo consistência de posição e
 * disparo de eventos de automação.
 */
final class ChangeNegotiationStageAction
{
    public function __construct(
        private readonly CRMNegotiationStatusService $statusService,
        private readonly CRMNegotiationPositionService $positionService,
        private readonly CRMNegotiationHistoryService $historyService,
        private readonly ListCRMNegotiationsAction $listAction,
    ) {}

    /**
     * Mover negociação para outra etapa e recalcular a ordenação da coluna.
     */
    public function move(string $tenantId, string $id, string $stepId, ?int $position = null): CRMNegotiation
    {
        return DB::transaction(function () use ($tenantId, $id, $stepId, $position): CRMNegotiation {
            $negotiation = $this->listAction->find($tenantId, $id);
            $currentStepId = (string) ($negotiation->crm_negotiation_funnel_step_id ?? '');

            CRMNegotiationFunnelStep::query()
                ->where('tenant_id', $tenantId)
                ->where('crm_negotiation_funnel_id', $negotiation->crm_negotiation_funnel_id)
                ->findOrFail($stepId);

            $targetNegotiations = CRMNegotiation::query()
                ->where('tenant_id', $tenantId)
                ->where('crm_negotiation_funnel_step_id', $stepId)
                ->where('id', '!=', $negotiation->id)
                ->orderBy('position')
                ->orderBy('created_at')
                ->get();

            $targetCount = $targetNegotiations->count();
            $finalPosition = $position ?? ($targetCount + 1);
            $finalPosition = max(1, min($finalPosition, $targetCount + 1));

            $ordered = $targetNegotiations->values()->all();
            array_splice($ordered, $finalPosition - 1, 0, [$negotiation]);
            $this->positionService->bulkReposition($tenantId, $stepId, $ordered);

            if ($currentStepId !== '' && $currentStepId !== $stepId) {
                $this->positionService->recalculatePositions($tenantId, $currentStepId);
                AutopilotTriggerFired::dispatch(
                    $tenantId,
                    AutopilotTriggerType::NEGOTIATION_STAGE_CHANGED,
                    [
                        'negotiation_id' => (string) $negotiation->id,
                        'from_step' => $currentStepId,
                        'to_step' => (string) $stepId,
                        'funnel_id' => (string) ($negotiation->crm_negotiation_funnel_id ?? ''),
                        'source_type' => 'negotiation',
                    ],
                    (string) $negotiation->id,
                );

                $this->historyService->record(
                    $negotiation,
                    sprintf('%s moveu a negociação de etapa.', $this->historyService->actorName())
                );
            }

            return $negotiation->refresh()->load(['company', 'contact', 'funnel', 'step', 'tags', 'user']);
        });
    }

    /**
     * Reordenar posições de negociações a partir de uma lista de ids e posições.
     *
     * @param  array<int, array{id: string, position: int}>  $items  Lista com id e posição desejada
     */
    public function reorder(string $tenantId, array $items): void
    {
        DB::transaction(function () use ($tenantId, $items): void {
            foreach ($items as $item) {
                CRMNegotiation::query()
                    ->where('tenant_id', $tenantId)
                    ->where('id', $item['id'])
                    ->update(['position' => (int) $item['position']]);
            }
        });
    }

    /**
     * Marcar negociação como ganha e disparar eventos de desfecho.
     */
    public function markWon(string $tenantId, string $id): CRMNegotiation
    {
        $negotiation = $this->listAction->find($tenantId, $id);
        $this->statusService->apply($negotiation, CRMNegotiationStatus::WON, null);
        $this->statusService->dispatchOutcomeTrigger($tenantId, $negotiation, AutopilotTriggerType::NEGOTIATION_WON);
        NegotiationWonEvent::dispatch(
            $tenantId,
            (string) $negotiation->id,
            (string) $negotiation->title,
            (float) ($negotiation->amount ?? 0),
        );

        if ($negotiation->crm_contact_id) {
            $this->statusService->broadcastStatusChanged(
                $tenantId,
                (string) $negotiation->crm_contact_id,
                [
                    'negotiation_id' => $negotiation->id,
                    'status' => $negotiation->status->value,
                    'closed_at' => $negotiation->closed_at?->toISOString(),
                ]
            );
        }

        $this->historyService->record(
            $negotiation,
            sprintf('%s marcou a negociação como ganha.', $this->historyService->actorName())
        );

        return $negotiation;
    }

    /**
     * Marcar negociação como perdida e registrar motivo quando informado.
     */
    public function markLost(string $tenantId, string $id, ?string $reasonId): CRMNegotiation
    {
        $negotiation = $this->listAction->find($tenantId, $id);
        $this->statusService->apply($negotiation, CRMNegotiationStatus::LOST, $reasonId);
        $this->statusService->dispatchOutcomeTrigger($tenantId, $negotiation, AutopilotTriggerType::NEGOTIATION_LOST);
        NegotiationLostEvent::dispatch(
            $tenantId,
            (string) $negotiation->id,
            (string) $negotiation->title,
            (float) ($negotiation->amount ?? 0),
        );

        if ($negotiation->crm_contact_id) {
            $this->statusService->broadcastStatusChanged(
                $tenantId,
                (string) $negotiation->crm_contact_id,
                [
                    'negotiation_id' => $negotiation->id,
                    'status' => $negotiation->status->value,
                    'reason_loss_id' => $negotiation->crm_reason_loss_id,
                    'closed_at' => $negotiation->closed_at?->toISOString(),
                ]
            );
        }

        $this->historyService->record(
            $negotiation,
            sprintf('%s marcou a negociação como perdida.', $this->historyService->actorName())
        );

        return $negotiation;
    }

    /**
     * Reabrir negociação, removendo fechamento e status final.
     */
    public function reopen(string $tenantId, string $id): CRMNegotiation
    {
        $negotiation = $this->listAction->find($tenantId, $id);
        $this->statusService->apply($negotiation, CRMNegotiationStatus::OPEN, null);

        if ($negotiation->crm_contact_id) {
            $this->statusService->broadcastStatusChanged(
                $tenantId,
                (string) $negotiation->crm_contact_id,
                [
                    'negotiation_id' => $negotiation->id,
                    'status' => $negotiation->status->value,
                    'closed_at' => null,
                ]
            );
        }

        $this->historyService->record(
            $negotiation,
            sprintf('%s reabriu a negociação.', $this->historyService->actorName())
        );

        return $negotiation;
    }
}
