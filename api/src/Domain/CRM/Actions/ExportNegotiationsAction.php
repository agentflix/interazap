<?php

declare(strict_types=1);

namespace Domain\CRM\Actions;

use Domain\CRM\Models\CRMNegotiation;
use Domain\CRM\Services\CRMNegotiationFilterService;
use Illuminate\Database\Eloquent\Builder;

/**
 * Caso de uso de exportação de negociações CRM.
 *
 * Retorna uma query builder pronta para iteração em memória baixa ou streaming,
 * sem aplicar paginação.
 */
final class ExportNegotiationsAction
{
    public function __construct(
        private readonly CRMNegotiationFilterService $filterService,
    ) {}

    /**
     * Constrói a query de exportação com filtros aplicados, sem paginação.
     *
     * @param  array<string, mixed>  $filters  Filtros do mesmo formato da listagem
     * @return Builder<CRMNegotiation> Query pronta para iteração
     */
    public function query(string $tenantId, array $filters = []): Builder
    {
        $query = CRMNegotiation::query()
            ->where('tenant_id', $tenantId)
            ->with(['company', 'contact', 'funnel', 'step', 'user']);

        $this->filterService->apply($query, $filters, false);

        return $query->orderByDesc('created_at');
    }
}
