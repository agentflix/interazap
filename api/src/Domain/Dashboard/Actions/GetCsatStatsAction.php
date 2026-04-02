<?php

declare(strict_types=1);

namespace Domain\Dashboard\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Aggregates CSAT stats for the dashboard.
 */
final class GetCsatStatsAction
{
    /**
     * Aggregates CSAT stats including average rating, total evaluations and distribution.
     *
     * @param  string  $tenantId  Tenant UUID
     * @param  Carbon  $from  Start date
     * @param  Carbon  $to  End date
     * @return array<string, mixed>
     */
    public function execute(string $tenantId, Carbon $from, Carbon $to): array
    {
        return Cache::remember(
            "dashboard:{$tenantId}:{$from->toDateString()}:{$to->toDateString()}:".class_basename(self::class),
            120,
            function () use ($tenantId, $from, $to): array {
                $aggregate = DB::table('chat_ticket_evaluations')
                    ->where('tenant_id', $tenantId)
                    ->whereBetween('submitted_at', [$from, $to])
                    ->where('rating', '>', 0)
                    ->selectRaw('AVG(rating) as average_rating')
                    ->selectRaw('COUNT(*) as total_evaluations')
                    ->selectRaw('SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as rating_1')
                    ->selectRaw('SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as rating_2')
                    ->selectRaw('SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as rating_3')
                    ->selectRaw('SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as rating_4')
                    ->selectRaw('SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as rating_5')
                    ->first();

                return [
                    'average_rating' => round((float) ($aggregate->average_rating ?? 0), 2),
                    'total_evaluations' => (int) ($aggregate->total_evaluations ?? 0),
                    'distribution' => [
                        1 => (int) ($aggregate->rating_1 ?? 0),
                        2 => (int) ($aggregate->rating_2 ?? 0),
                        3 => (int) ($aggregate->rating_3 ?? 0),
                        4 => (int) ($aggregate->rating_4 ?? 0),
                        5 => (int) ($aggregate->rating_5 ?? 0),
                    ],
                ];
            }
        );
    }
}
