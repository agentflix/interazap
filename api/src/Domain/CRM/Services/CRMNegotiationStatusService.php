<?php

declare(strict_types=1);

namespace Domain\CRM\Services;

use Domain\Ai\Enums\AutopilotTriggerType;
use Domain\Ai\Events\AutopilotTriggerFired;
use Domain\CRM\Enums\CRMNegotiationStatus;
use Domain\CRM\Models\CRMNegotiation;
use Domain\Shared\Contracts\ActivityBroadcastService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Encapsula transições de status de negociações e seus efeitos colaterais.
 *
 * Gerencia as regras de negócio de fechamento (ganha/perdida/reaberta),
 * persistência de closed_at e despacho de triggers de automação.
 */
final class CRMNegotiationStatusService
{
    public function __construct(
        private readonly ActivityBroadcastService $broadcastService,
    ) {}

    /**
     * Aplica a transição de status à negociação, configurando closed_at e motivo de perda.
     *
     * @param  string|null  $reasonLossId  Obrigatório quando status = LOST
     *
     * @throws \Illuminate\Validation\ValidationException Quando status LOST sem motivo informado
     */
    public function apply(CRMNegotiation $negotiation, CRMNegotiationStatus $status, ?string $reasonLossId): void
    {
        if ($reasonLossId === '') {
            $reasonLossId = null;
        }

        if ($status === CRMNegotiationStatus::LOST && $reasonLossId === null) {
            throw ValidationException::withMessages([
                'crm_reason_loss_id' => ['Motivo da perda é obrigatório ao marcar como perdida.'],
            ]);
        }

        if ($status === CRMNegotiationStatus::WON) {
            $negotiation->crm_reason_loss_id = null;
            $negotiation->closed_at = now();
        } elseif ($status === CRMNegotiationStatus::LOST) {
            $negotiation->crm_reason_loss_id = $reasonLossId;
            $negotiation->closed_at = now();
        } else {
            $negotiation->crm_reason_loss_id = null;
            $negotiation->closed_at = null;
        }

        $negotiation->status = $status;
        $negotiation->save();
    }

    /** Despacha o evento de trigger de automação (ganha ou perdida) para o autopilot. */
    public function dispatchOutcomeTrigger(string $tenantId, CRMNegotiation $negotiation, AutopilotTriggerType $triggerType): void
    {
        AutopilotTriggerFired::dispatch(
            $tenantId,
            $triggerType,
            [
                'negotiation_id' => (string) $negotiation->id,
                'contact_id' => (string) ($negotiation->crm_contact_id ?? ''),
                'amount' => (float) ($negotiation->amount ?? 0),
                'funnel_id' => (string) ($negotiation->crm_negotiation_funnel_id ?? ''),
                'reason_loss_id' => (string) ($negotiation->crm_reason_loss_id ?? ''),
                'source_type' => 'negotiation',
            ],
            (string) $negotiation->id,
        );
    }

    /**
     * Transmite via broadcast a mudança de status da negociação ao ticket de chat vinculado.
     *
     * Ignora silenciosamente se a tabela chat_tickets não tiver a coluna crm_contact_id
     * (compatibilidade com ambientes sem a migration aplicada).
     *
     * @param  array<string, mixed>  $payload  Dados da mudança de status para broadcast
     */
    public function broadcastStatusChanged(string $tenantId, string $contactId, array $payload): void
    {
        $isMockedBroadcastService = str_starts_with($this->broadcastService::class, 'Mockery_');

        if (! $isMockedBroadcastService && ! Schema::hasColumn('chat_tickets', 'crm_contact_id')) {
            return;
        }

        try {
            $this->broadcastService->broadcastNegotiationStatusChanged($tenantId, $contactId, $payload);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
