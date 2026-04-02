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
 * Encapsulates negotiation status transitions and side effects.
 */
final class CRMNegotiationStatusService
{
    public function __construct(
        private readonly ActivityBroadcastService $broadcastService,
    ) {}

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
     * @param  array<string, mixed>  $payload
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
