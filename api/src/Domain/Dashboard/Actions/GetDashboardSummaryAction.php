<?php

declare(strict_types=1);

namespace Domain\Dashboard\Actions;

use Domain\Chat\Enums\ChatTicketStatus;
use Domain\CRM\Enums\CRMNegotiationStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Coleta os KPIs consolidados para o painel principal do dashboard.
 *
 * Retorna receita ganha, pipeline aberto, contagem de tickets ativos e CSAT médio.
 */
final class GetDashboardSummaryAction
{
    /**
     * Executa a coleta de KPIs do dashboard para o período informado.
     *
     * @param  string  $tenantId  UUID do tenant
     * @param  Carbon  $from  Data de início do período
     * @param  Carbon  $to  Data de fim do período
     * @return array<string, float|int> Com chaves total_revenue_won, pipeline_open_value, active_tickets_count e csat_average
     */
    public function execute(string $tenantId, Carbon $from, Carbon $to): array
    {
        $cacheKey = "dashboard:{$tenantId}:{$from->toDateString()}:{$to->toDateString()}:".class_basename(self::class);

        return Cache::remember(
            $cacheKey,
            120,
            function () use ($tenantId, $from, $to): array {
                $totalRevenueWon = (float) DB::table('crm_negotiations')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at')
                    ->where('status', CRMNegotiationStatus::WON->value)
                    ->whereBetween('closed_at', [$from, $to])
                    ->sum('amount');

                $pipelineOpenValue = (float) DB::table('crm_negotiations')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at')
                    ->where('status', CRMNegotiationStatus::OPEN->value)
                    ->whereBetween('created_at', [$from, $to])
                    ->sum('amount');

                $activeTicketsCount = (int) DB::table('chat_tickets')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at')
                    ->whereIn('status', array_column(ChatTicketStatus::active(), 'value'))
                    ->whereBetween('created_at', [$from, $to])
                    ->count();

                $csatAverage = DB::table('chat_ticket_evaluations')
                    ->where('tenant_id', $tenantId)
                    ->whereBetween('submitted_at', [$from, $to])
                    ->where('rating', '>', 0)
                    ->avg('rating');

                return [
                    'total_revenue_won' => $totalRevenueWon,
                    'pipeline_open_value' => $pipelineOpenValue,
                    'active_tickets_count' => $activeTicketsCount,
                    'csat_average' => $csatAverage ? round((float) $csatAverage, 2) : 0.0,
                ];
            }
        );
    }
}
