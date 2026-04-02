<?php

declare(strict_types=1);

namespace Domain\CRM\Actions;

use Domain\CRM\Models\CRMNegotiation;
use Domain\CRM\Services\CRMNegotiationFilterService;
use Illuminate\Database\Eloquent\Builder;

/**
 * Export use-case for CRM negotiations.
 */
final class ExportNegotiationsAction
{
    public function __construct(
        private readonly CRMNegotiationFilterService $filterService,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<CRMNegotiation>
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
