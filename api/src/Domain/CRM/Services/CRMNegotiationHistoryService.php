<?php

declare(strict_types=1);

namespace Domain\CRM\Services;

use Domain\CRM\Models\CRMCompany;
use Domain\CRM\Models\CRMContact;
use Domain\CRM\Models\CRMNegotiation;
use Domain\CRM\Models\CRMNegotiationFunnel;
use Domain\CRM\Models\CRMNegotiationFunnelStep;
use Illuminate\Support\Str;

/**
 * Formats and persists negotiation history notes.
 */
final class CRMNegotiationHistoryService
{
    /**
     * @param  array<string, mixed>  $before
     */
    public function recordUpdate(string $tenantId, CRMNegotiation $negotiation, array $before): void
    {
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

            if ($this->normalizeValue($oldRaw) === $this->normalizeValue($newRaw)) {
                continue;
            }

            $oldValue = $this->formatValue($tenantId, $attribute, $oldRaw);
            $newValue = $this->formatValue($tenantId, $attribute, $newRaw);
            $changes[] = sprintf('%s de "%s" para "%s"', $label, $oldValue, $newValue);
        }

        if ($changes === []) {
            return;
        }

        $this->record($negotiation, sprintf('%s alterou %s.', $this->actorName(), implode('; ', $changes)));
    }

    public function record(CRMNegotiation $negotiation, string $content): void
    {
        $negotiation->historyNotes()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $negotiation->tenant_id,
            'auth_user_id' => auth('sanctum')->id(),
            'content' => $content,
        ]);
    }

    public function actorName(): string
    {
        return (string) data_get(auth('sanctum')->user(), 'name', 'Usuário');
    }

    private function formatValue(string $tenantId, string $attribute, mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'não informado';
        }

        $normalizedValue = $this->normalizeValue($value);

        return match ($attribute) {
            'crm_negotiation_funnel_id' => $this->resolveFunnelName($tenantId, $normalizedValue),
            'crm_negotiation_funnel_step_id' => $this->resolveStepName($tenantId, $normalizedValue),
            'crm_contact_id' => $this->resolveContactName($tenantId, $normalizedValue),
            'crm_company_id' => $this->resolveCompanyName($tenantId, $normalizedValue),
            'auth_user_id' => $this->resolveUserName($tenantId, $normalizedValue),
            default => $normalizedValue,
        };
    }

    private function normalizeValue(mixed $value): string
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

        $step = CRMNegotiationFunnelStep::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $stepId)
            ->first();

        return (string) data_get($step, 'name', $stepId);
    }

    private function resolveContactName(string $tenantId, ?string $contactId): string
    {
        if ($contactId === null || $contactId === '') {
            return 'não informado';
        }

        $contact = CRMContact::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $contactId)
            ->first();

        return (string) data_get($contact, 'name', $contactId);
    }

    private function resolveCompanyName(string $tenantId, ?string $companyId): string
    {
        if ($companyId === null || $companyId === '') {
            return 'não informado';
        }

        $company = CRMCompany::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $companyId)
            ->first();

        return (string) data_get($company, 'name', $companyId);
    }

    private function resolveUserName(string $tenantId, ?string $userId): string
    {
        if ($userId === null || $userId === '') {
            return 'não informado';
        }

        $user = \Domain\Auth\Models\AuthUser::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $userId)
            ->first();

        return (string) data_get($user, 'name', $userId);
    }
}
