<?php

declare(strict_types=1);

namespace Domain\CRM\Actions;

use Domain\CRM\DTOs\CRMNegotiationDTO;
use Domain\CRM\Enums\CRMNegotiationStatus;
use Domain\CRM\Models\CRMContact;
use Domain\CRM\Models\CRMNegotiation;
use Domain\CRM\Models\CRMNegotiationFunnel;
use Domain\CRM\Models\CRMNegotiationFunnelStep;
use Domain\CRM\Services\CRMNegotiationStatusService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Create use-case for CRM negotiations.
 */
final class CreateCRMNegotiationAction
{
    public function __construct(
        private readonly CRMNegotiationStatusService $statusService,
    ) {}

    /**
     * Criar negociação validando integridade das entidades relacionadas no tenant.
     */
    public function create(string $tenantId, CRMNegotiationDTO $dto): CRMNegotiation
    {
        return DB::transaction(function () use ($tenantId, $dto): CRMNegotiation {
            $this->assertRelatedEntitiesIntegrity($tenantId, $dto);

            $position = $dto->position;
            if ($position <= 0) {
                $position = (int) CRMNegotiation::query()
                    ->where('tenant_id', $tenantId)
                    ->where('crm_negotiation_funnel_step_id', $dto->crm_negotiation_funnel_step_id)
                    ->max('position') + 1;
            }

            $status = CRMNegotiationStatus::from($dto->status);

            $negotiation = CRMNegotiation::query()->create([
                'id' => (string) Str::orderedUuid(),
                'tenant_id' => $tenantId,
                ...$dto->toArray(),
                'position' => $position,
            ]);

            $this->statusService->apply($negotiation, $status, $dto->crm_reason_loss_id);

            return $negotiation->load(['company', 'contact', 'funnel', 'step', 'tags', 'customFieldValues.field', 'user']);
        });
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
