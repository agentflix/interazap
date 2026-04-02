<?php

declare(strict_types=1);

namespace Domain\Reports\Actions;

use Domain\Reports\DTOs\ReportsFilterDTO;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Relatório de Performance de Produtos.
 *
 * Retorna vendas por produto, propostas, pipeline e estoque.
 */
final class GetProductPerformanceReportAction extends AbstractReportAction
{
    /**
     * Executa o relatório de performance de produtos.
     *
     * @return array<string, mixed>
     */
    public function execute(ReportsFilterDTO $dto): array
    {
        $cacheKey = sprintf(
            'reports:%s:product:%s',
            $dto->tenantId,
            md5(serialize([
                $dto->startDate->toDateString(),
                $dto->endDate->toDateString(),
                $dto->productId,
                $dto->funnelId,
            ]))
        );

        /** @var array<string, mixed> */
        return Cache::remember($cacheKey, $this->cacheTtl(), function () use ($dto): array {
            return [
                'products' => $this->getProducts($dto),
                'proposals' => $this->getProposalStats($dto),
            ];
        });
    }

    /**
     * Performance de vendas por produto.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getProducts(ReportsFilterDTO $dto): array
    {
        $query = DB::table('crm_negotiation_products as np')
            ->join('crm_negotiations as n', 'n.id', '=', 'np.crm_negotiation_id')
            ->leftJoin('crm_products as p', 'p.id', '=', 'np.crm_product_id')
            ->where('np.tenant_id', $dto->tenantId)
            ->whereNull('n.deleted_at')
            ->whereBetween('n.created_at', [$dto->startDate, $dto->endDate]);

        if ($dto->productId !== null) {
            $query->where('np.crm_product_id', $dto->productId);
        }

        if ($dto->funnelId !== null) {
            $query->where('n.crm_negotiation_funnel_id', $dto->funnelId);
        }

        return $query
            ->selectRaw("
                COALESCE(p.name, np.name) as product_name,
                COALESCE(p.type, 'product') as product_type,
                SUM(CASE WHEN n.status = 'won' THEN np.quantity ELSE 0 END) as sold_qty,
                SUM(CASE WHEN n.status = 'won' THEN np.total ELSE 0 END) as total_revenue,
                AVG(np.unit_price) as avg_unit_price,
                SUM(CASE WHEN n.status = 'open' THEN np.quantity ELSE 0 END) as pipeline_qty,
                SUM(CASE WHEN n.status = 'open' THEN np.total ELSE 0 END) as pipeline_value,
                COALESCE(p.stock_quantity, 0) as current_stock
            ")
            ->groupBy('np.crm_product_id', 'p.name', 'np.name', 'p.type', 'p.stock_quantity')
            ->orderByDesc('total_revenue')
            ->get()
            ->map(fn (object $row): array => [
                'product_name' => (string) $row->product_name,
                'type' => (string) $row->product_type,
                'sold_qty' => (int) $row->sold_qty,
                'total_revenue' => round((float) $row->total_revenue, 2),
                'avg_unit_price' => round((float) ($row->avg_unit_price ?? 0), 2),
                'pipeline_qty' => (int) $row->pipeline_qty,
                'pipeline_value' => round((float) $row->pipeline_value, 2),
                'current_stock' => (int) $row->current_stock,
            ])->all();
    }

    /**
     * Propostas por produto: enviadas, aceitas, rejeitadas, taxa de aceitação.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getProposalStats(ReportsFilterDTO $dto): array
    {
        $query = DB::table('crm_proposals as pr')
            ->join('crm_negotiations as n', 'n.id', '=', 'pr.crm_negotiation_id')
            ->join('crm_negotiation_products as np', 'np.crm_negotiation_id', '=', 'n.id')
            ->leftJoin('crm_products as p', 'p.id', '=', 'np.crm_product_id')
            ->where('pr.tenant_id', $dto->tenantId)
            ->whereNull('n.deleted_at')
            ->whereBetween('pr.created_at', [$dto->startDate, $dto->endDate]);

        if ($dto->productId !== null) {
            $query->where('np.crm_product_id', $dto->productId);
        }

        return $query
            ->selectRaw('
                COALESCE(p.name, np.name) as product_name,
                COUNT(CASE WHEN pr.sent_at IS NOT NULL THEN 1 END) as sent,
                COUNT(CASE WHEN pr.accepted_at IS NOT NULL THEN 1 END) as accepted,
                COUNT(CASE WHEN pr.rejected_at IS NOT NULL THEN 1 END) as rejected
            ')
            ->groupBy('np.crm_product_id', 'p.name', 'np.name')
            ->orderByDesc('sent')
            ->get()
            ->map(function (object $row): array {
                $sent = max((int) $row->sent, 1);

                return [
                    'product_name' => (string) $row->product_name,
                    'sent' => (int) $row->sent,
                    'accepted' => (int) $row->accepted,
                    'rejected' => (int) $row->rejected,
                    'acceptance_rate' => round(((int) $row->accepted / $sent) * 100, 2),
                ];
            })->all();
    }
}
