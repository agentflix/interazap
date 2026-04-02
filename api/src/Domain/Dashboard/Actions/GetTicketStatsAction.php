<?php

declare(strict_types=1);

namespace Domain\Dashboard\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Aggregates ticket stats for the dashboard.
 */
final class GetTicketStatsAction
{
    /**
     * @return array<string, mixed>
     */
    public function execute(string $tenantId, Carbon $from, Carbon $to): array
    {
        return Cache::remember(
            "dashboard:{$tenantId}:{$from->toDateString()}:{$to->toDateString()}:".class_basename(self::class),
            120,
            function () use ($tenantId, $from, $to): array {
                $dailyVolume = DB::table('chat_tickets')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at')
                    ->whereBetween('created_at', [$from, $to])
                    ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
                    ->groupByRaw('DATE(created_at)')
                    ->orderBy('date')
                    ->get()
                    ->map(fn ($row) => [
                        'date' => (string) $row->date,
                        'count' => (int) $row->count,
                    ])
                    ->all();

                $priorityRows = DB::table('chat_tickets')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at')
                    ->whereBetween('created_at', [$from, $to])
                    ->selectRaw('priority, COUNT(*) as count')
                    ->groupBy('priority')
                    ->get();

                // TODO: use enum when available (ChatTicketPriority)
                $byPriority = [
                    'low' => 0,
                    'normal' => 0,
                    'high' => 0,
                    'urgent' => 0,
                ];

                foreach ($priorityRows as $row) {
                    $priority = (string) $row->priority;
                    if (array_key_exists($priority, $byPriority)) {
                        $byPriority[$priority] = (int) $row->count;
                    }
                }

                $slaBase = DB::table('chat_tickets')
                    ->leftJoin('chat_tickets_extended as cte', 'cte.ticket_id', '=', 'chat_tickets.id')
                    ->where('chat_tickets.tenant_id', $tenantId)
                    ->whereNull('chat_tickets.deleted_at')
                    ->whereNotNull('chat_tickets.closed_at')
                    ->whereBetween('chat_tickets.closed_at', [$from, $to]);

                $totalClosed = (int) (clone $slaBase)->count();
                $compliantClosed = (int) (clone $slaBase)
                    ->where('cte.sla_first_response_breached', false)
                    ->where('cte.sla_resolution_breached', false)
                    ->count();

                $slaComplianceRate = $totalClosed > 0
                    ? round(($compliantClosed / $totalClosed) * 100, 2)
                    : 0.0;

                $avgFirstResponseMinutes = DB::table('chat_tickets')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at')
                    ->whereNotNull('first_response_at')
                    ->whereBetween('created_at', [$from, $to])
                    ->selectRaw('AVG(EXTRACT(EPOCH FROM (first_response_at - created_at)) / 60) as avg_minutes')
                    ->value('avg_minutes');

                return [
                    'daily_volume' => $dailyVolume,
                    'by_priority' => $byPriority,
                    'sla_compliance_rate' => $slaComplianceRate,
                    'avg_first_response_minutes' => $avgFirstResponseMinutes
                        ? round((float) $avgFirstResponseMinutes, 2)
                        : 0.0,
                ];
            }
        );
    }
}
