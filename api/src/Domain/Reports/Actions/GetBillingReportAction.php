<?php

declare(strict_types=1);

namespace Domain\Reports\Actions;

use Domain\Reports\DTOs\ReportsFilterDTO;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Relatório de Faturamento.
 *
 * Retorna MRR, receita por plano, inadimplência, métodos de pagamento e alertas de vencimento.
 */
final class GetBillingReportAction extends AbstractReportAction
{
    /**
     * Executa o relatório de faturamento.
     *
     * @return array<string, mixed>
     */
    public function execute(ReportsFilterDTO $dto): array
    {
        $cacheKey = sprintf(
            'reports:%s:billing:%s',
            $dto->tenantId,
            md5(serialize([
                $dto->startDate->toDateString(),
                $dto->endDate->toDateString(),
            ]))
        );

        /** @var array<string, mixed> */
        return Cache::remember($cacheKey, $this->cacheTtl(), function () use ($dto): array {
            return [
                'summary' => $this->getSummary($dto),
                'by_payment_method' => $this->getByPaymentMethod($dto),
                'monthly_revenue' => $this->getMonthlyRevenue($dto),
                'upcoming_due' => $this->getUpcomingDue($dto),
            ];
        });
    }

    /**
     * Totais de faturamento.
     *
     * @return array<string, mixed>
     */
    private function getSummary(ReportsFilterDTO $dto): array
    {
        $row = $this->baseQuery($dto)
            ->selectRaw("
                COALESCE(SUM(i.amount), 0) as total_invoiced,
                COALESCE(SUM(CASE WHEN i.status = 'paid' THEN i.amount ELSE 0 END), 0) as total_paid,
                COALESCE(SUM(CASE WHEN i.status = 'pending' THEN i.amount ELSE 0 END), 0) as total_pending,
                COALESCE(SUM(CASE WHEN i.status = 'overdue' THEN i.amount ELSE 0 END), 0) as total_overdue,
                COUNT(i.id) as total_count,
                COUNT(CASE WHEN i.status = 'overdue' THEN 1 END) as overdue_count,
                AVG(CASE WHEN i.paid_at IS NOT NULL THEN EXTRACT(EPOCH FROM (i.paid_at - i.created_at)) / 86400 END) as avg_days_to_pay
            ")
            ->first();

        $totalCount = max((int) ($row->total_count ?? 0), 1);
        $overdueCount = (int) ($row->overdue_count ?? 0);

        return [
            'total_invoiced' => round((float) ($row->total_invoiced ?? 0), 2),
            'total_paid' => round((float) ($row->total_paid ?? 0), 2),
            'total_pending' => round((float) ($row->total_pending ?? 0), 2),
            'total_overdue' => round((float) ($row->total_overdue ?? 0), 2),
            'overdue_rate' => round(($overdueCount / $totalCount) * 100, 2),
            'avg_days_to_pay' => round((float) ($row->avg_days_to_pay ?? 0), 1),
        ];
    }

    /**
     * Por método de pagamento.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getByPaymentMethod(ReportsFilterDTO $dto): array
    {
        return $this->baseQuery($dto)
            ->whereNotNull('i.payment_method')
            ->selectRaw('i.payment_method, COUNT(i.id) as count, COALESCE(SUM(i.amount), 0) as total_amount')
            ->groupBy('i.payment_method')
            ->orderByDesc('total_amount')
            ->get()
            ->map(fn (object $row): array => [
                'payment_method' => (string) $row->payment_method,
                'count' => (int) $row->count,
                'total_amount' => round((float) $row->total_amount, 2),
            ])->all();
    }

    /**
     * Evolução mensal de receita.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getMonthlyRevenue(ReportsFilterDTO $dto): array
    {
        return $this->baseQuery($dto)
            ->selectRaw("
                i.reference_month as period,
                COALESCE(SUM(i.amount), 0) as total_amount,
                COUNT(i.id) as count,
                COALESCE(SUM(CASE WHEN i.status = 'paid' THEN i.amount ELSE 0 END), 0) as paid_amount
            ")
            ->groupBy('i.reference_month')
            ->orderBy('i.reference_month')
            ->get()
            ->map(fn (object $row): array => [
                'period' => (string) $row->period,
                'total_amount' => round((float) $row->total_amount, 2),
                'paid_amount' => round((float) $row->paid_amount, 2),
                'count' => (int) $row->count,
            ])->all();
    }

    /**
     * Faturas vencendo nos próximos 7 dias.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getUpcomingDue(ReportsFilterDTO $dto): array
    {
        return DB::table('billing_invoices as i')
            ->where('i.tenant_id', $dto->tenantId)
            ->whereIn('i.status', ['pending', 'draft'])
            ->whereBetween('i.due_date', [now()->toDateString(), now()->addDays(7)->toDateString()])
            ->select(['i.id', 'i.reference_month', 'i.amount', 'i.due_date', 'i.status'])
            ->orderBy('i.due_date')
            ->get()
            ->map(fn (object $row): array => [
                'id' => (string) $row->id,
                'reference_month' => (string) $row->reference_month,
                'amount' => round((float) $row->amount, 2),
                'due_date' => (string) $row->due_date,
                'status' => (string) $row->status,
            ])->all();
    }

    /**
     * Query base para billing_invoices.
     */
    private function baseQuery(ReportsFilterDTO $dto): \Illuminate\Database\Query\Builder
    {
        return DB::table('billing_invoices as i')
            ->where('i.tenant_id', $dto->tenantId)
            ->whereBetween('i.created_at', [$dto->startDate, $dto->endDate]);
    }
}
