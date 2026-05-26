<?php

declare(strict_types=1);

namespace Domain\Dashboard\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Retorna dados de receita mensal para exibição em gráfico no dashboard.
 *
 * Agrega negociações ganhas e abertas agrupadas por mês dentro do período solicitado.
 */
final class GetRevenueChartAction
{
    /**
     * Executa a geração dos dados do gráfico de receita.
     *
     * @param  string  $tenantId  UUID do tenant
     * @param  Carbon  $from  Data de início do período
     * @param  Carbon  $to  Data de fim do período
     * @return array<int, array<string, float|int>> Lista com month, year, won_amount e open_amount
     */
    public function execute(string $tenantId, Carbon $from, Carbon $to): array
    {
        return Cache::remember(
            "dashboard:{$tenantId}:{$from->toDateString()}:{$to->toDateString()}:".class_basename(self::class),
            120,
            function () use ($tenantId, $from, $to): array {
                $rows = DB::table('crm_negotiations')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at')
                    ->whereBetween(DB::raw('COALESCE(closed_at, expected_close, created_at)'), [$from, $to])
                    ->selectRaw("DATE_TRUNC('month', COALESCE(closed_at, expected_close, created_at)) as month")
                    ->selectRaw("COALESCE(SUM(CASE WHEN status = 'won' THEN amount ELSE 0 END), 0) as won_amount")
                    ->selectRaw("COALESCE(SUM(CASE WHEN status = 'open' THEN amount ELSE 0 END), 0) as open_amount")
                    ->groupByRaw("DATE_TRUNC('month', COALESCE(closed_at, expected_close, created_at))")
                    ->orderBy('month')
                    ->get();

                return $rows
                    ->map(function ($row): array {
                        $monthDate = Carbon::parse($row->month);

                        return [
                            'month' => (int) $monthDate->format('m'),
                            'year' => (int) $monthDate->format('Y'),
                            'won_amount' => (float) $row->won_amount,
                            'open_amount' => (float) $row->open_amount,
                        ];
                    })
                    ->all();
            }
        );
    }
}
