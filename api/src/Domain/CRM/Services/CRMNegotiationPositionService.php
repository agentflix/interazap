<?php

declare(strict_types=1);

namespace Domain\CRM\Services;

use Domain\CRM\Models\CRMNegotiation;
use Illuminate\Support\Facades\DB;

/**
 * Centraliza o recálculo de posição e movimentação em massa de negociações no kanban.
 *
 * Usa UPDATE com CASE para reposicionamento atômico, garantindo consistência
 * de ordenação mesmo durante operações concorrentes de drag-and-drop.
 */
final class CRMNegotiationPositionService
{
    /**
     * Recalcula as posições de todas as negociações em uma etapa, normalizando para 1..N.
     *
     * @param  string  $tenantId  ID do tenant
     * @param  string|null  $stepId  ID da etapa do funil
     */
    public function recalculatePositions(string $tenantId, ?string $stepId): void
    {
        if ($stepId === null || $stepId === '') {
            return;
        }

        $negotiations = CRMNegotiation::query()
            ->where('tenant_id', $tenantId)
            ->where('crm_negotiation_funnel_step_id', $stepId)
            ->orderBy('position')
            ->orderBy('created_at')
            ->get();

        if ($negotiations->isEmpty()) {
            return;
        }

        $this->persistNegotiationPositions($tenantId, $stepId, $negotiations->all());
    }

    /**
     * Reposiciona em massa uma lista ordenada de negociações em uma etapa.
     *
     * @param  array<int, CRMNegotiation>  $ordered  Negociações na ordem desejada
     */
    public function bulkReposition(string $tenantId, string $stepId, array $ordered): void
    {
        if ($ordered === []) {
            return;
        }

        $this->persistNegotiationPositions($tenantId, $stepId, $ordered);
    }

    /**
     * @param  array<int, CRMNegotiation>  $ordered
     */
    private function persistNegotiationPositions(string $tenantId, string $stepId, array $ordered): void
    {
        if ($ordered === []) {
            return;
        }

        $timestamp = now();
        $ids = [];
        $caseClauses = [];
        $bindings = [$stepId];

        foreach ($ordered as $index => $item) {
            $id = (string) $item->id;
            $ids[] = $id;
            $caseClauses[] = 'WHEN ? THEN ?::integer';
            $bindings[] = $id;
            $bindings[] = $index + 1;
        }

        $bindings[] = $timestamp;
        $bindings[] = $tenantId;

        $inPlaceholders = implode(', ', array_fill(0, count($ids), '?'));
        $bindings = array_merge($bindings, $ids);

        $sql = sprintf(
            'UPDATE crm_negotiations SET crm_negotiation_funnel_step_id = ?, position = CASE id %s END, updated_at = ? WHERE tenant_id = ? AND id IN (%s)',
            implode(' ', $caseClauses),
            $inPlaceholders,
        );

        DB::update($sql, $bindings);
    }
}
