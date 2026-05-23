<?php

declare(strict_types=1);

namespace Domain\CRM\Actions;

use Domain\CRM\Models\CRMNegotiation;
use Domain\CRM\Models\CRMNegotiationFunnel;
use Domain\CRM\Models\CRMNegotiationFunnelStep;
use Domain\CRM\Services\CRMNegotiationFilterService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * List and read use-cases for CRM negotiations.
 */
final class ListCRMNegotiationsAction
{
    public function __construct(
        private readonly CRMNegotiationFilterService $filterService,
        private readonly ListCRMNegotiationsByStepAction $listByStep,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(string $tenantId, array $filters = []): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 15);

        $query = CRMNegotiation::query()
            ->select([
                'id',
                'tenant_id',
                'auth_user_id',
                'title',
                'amount',
                'status',
                'crm_contact_id',
                'crm_company_id',
                'crm_negotiation_funnel_id',
                'crm_negotiation_funnel_step_id',
                'crm_reason_loss_id',
                'position',
                'expected_close',
                'closed_at',
                'created_at',
                'updated_at',
            ])
            ->where('tenant_id', $tenantId)
            ->with(['company', 'contact', 'funnel', 'step', 'tags', 'user', 'customFieldValues.field']);

        $this->filterService->apply($query, $filters, false);

        return $query
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * Buscar uma negociação específica garantindo isolamento por tenant.
     */
    public function find(string $tenantId, string $id): CRMNegotiation
    {
        return CRMNegotiation::query()
            ->where('tenant_id', $tenantId)
            ->with(['company', 'contact', 'funnel', 'step', 'tags', 'customFieldValues.field', 'user'])
            ->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function kanban(string $tenantId, string $funnelId, array $filters = []): array
    {
        $perPage = (int) ($filters['per_page'] ?? 20);

        $funnel = CRMNegotiationFunnel::query()
            ->where('tenant_id', $tenantId)
            ->findOrFail($funnelId);

        $steps = CRMNegotiationFunnelStep::query()
            ->where('tenant_id', $tenantId)
            ->where('crm_negotiation_funnel_id', $funnelId)
            ->where('is_active', true)
            ->orderBy('order')
            ->get();

        // Aggregate query: busca contagem e soma por etapa sem carregar registros
        // Uma única query GROUP BY substitui o get() + groupBy em memória
        $aggregateQuery = CRMNegotiation::query()
            ->selectRaw('crm_negotiation_funnel_step_id, COUNT(*) as total_count, COALESCE(SUM(amount), 0) as total_value')
            ->where('tenant_id', $tenantId)
            ->where('crm_negotiation_funnel_id', $funnelId)
            ->groupBy('crm_negotiation_funnel_step_id');

        $this->filterService->applyForAggregate($aggregateQuery, $filters, false);

        // Negociações com amount > 0 devem ter ao menos um produto vinculado.
        $aggregateQuery->where(function (Builder $builder): void {
            $builder->where('amount', '<=', 0)
                ->orWhereHas('products', fn (Builder $productQuery) => $productQuery->whereNotNull('crm_product_id'));
        });

        $aggregates = $aggregateQuery->get()->keyBy('crm_negotiation_funnel_step_id');

        return [
            'funnel' => [
                'id' => $funnel->id,
                'name' => $funnel->name,
                'description' => $funnel->description,
                'is_active' => (bool) $funnel->is_active,
            ],
            'steps' => $steps->map(function (CRMNegotiationFunnelStep $step) use ($tenantId, $funnelId, $filters, $perPage, $aggregates): array {
                $agg = $aggregates->get($step->id);
                $totalCount = $agg ? (int) $agg->total_count : 0;
                $totalValue = $agg ? (float) $agg->total_value : 0.0;

                $page = $this->listByStep->execute(
                    $tenantId,
                    $funnelId,
                    $step->id,
                    null,
                    $perPage,
                    $filters,
                );

                return [
                    'id' => $step->id,
                    'crm_negotiation_funnel_id' => $step->crm_negotiation_funnel_id,
                    'name' => $step->name,
                    'color' => $step->color,
                    'is_active' => (bool) $step->is_active,
                    'order' => (int) $step->order,
                    'total_count' => $totalCount,
                    'total_value' => $totalValue,
                    'has_more' => $page['has_more'],
                    'next_cursor' => $page['next_cursor'],
                    'negotiations' => $page['negotiations'],
                ];
            })->values()->all(),
        ];
    }
}
