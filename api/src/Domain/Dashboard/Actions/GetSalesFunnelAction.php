<?php

declare(strict_types=1);

namespace Domain\Dashboard\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Builds the sales funnel breakdown by step.
 */
final class GetSalesFunnelAction
{
    private const DEFAULT_FUNNEL_COLOR = '#94a3b8';

    /**
     * Builds the sales funnel breakdown grouped by negotiation step.
     *
     * @param  string  $tenantId  Tenant UUID
     * @param  Carbon  $from  Start date
     * @param  Carbon  $to  End date
     * @return array<int, array<string, float|int|string>>
     */
    public function execute(string $tenantId, Carbon $from, Carbon $to): array
    {
        $cacheKey = "dashboard:{$tenantId}:{$from->toDateString()}:{$to->toDateString()}:".class_basename(self::class);

        return Cache::remember(
            $cacheKey,
            120,
            function () use ($tenantId, $from, $to): array {
                $rows = DB::table('crm_negotiations as n')
                    ->join('crm_negotiation_funnel_steps as s', 's.id', '=', 'n.crm_negotiation_funnel_step_id')
                    ->where('n.tenant_id', $tenantId)
                    ->where('s.tenant_id', $tenantId)
                    ->whereNull('n.deleted_at')
                    ->where('n.status', 'open')
                    ->whereBetween('n.created_at', [$from, $to])
                    ->selectRaw('s.name as step_name, COALESCE(s.color, ?) as step_color, COUNT(n.id) as count, COALESCE(SUM(n.amount), 0) as total_amount, s.order as step_order', [self::DEFAULT_FUNNEL_COLOR])
                    ->groupBy('s.name', 's.color', 's.order')
                    ->orderBy('s.order')
                    ->get();

                return $rows
                    ->map(fn ($row) => [
                        'step_name' => (string) $row->step_name,
                        'step_color' => (string) $row->step_color,
                        'count' => (int) $row->count,
                        'total_amount' => (float) $row->total_amount,
                    ])
                    ->all();
            }
        );
    }
}
