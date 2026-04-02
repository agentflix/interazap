<?php

declare(strict_types=1);

namespace Domain\Dashboard\Actions;

use Domain\CRM\Enums\CRMNegotiationStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Aggregates negotiation stats for the dashboard.
 */
final class GetNegotiationStatsAction
{
    private const TOP_LIMIT = 5;

    /**
     * @return array<string, mixed>
     */
    public function execute(string $tenantId, Carbon $from, Carbon $to): array
    {
        return Cache::remember(
            "dashboard:{$tenantId}:{$from->toDateString()}:{$to->toDateString()}:".class_basename(self::class),
            120,
            function () use ($tenantId, $from, $to): array {
                $base = DB::table('crm_negotiations')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at');

                $openCount = (int) (clone $base)
                    ->where('status', CRMNegotiationStatus::OPEN->value)
                    ->whereBetween('created_at', [$from, $to])
                    ->count();

                $wonCount = (int) (clone $base)
                    ->where('status', CRMNegotiationStatus::WON->value)
                    ->whereBetween('closed_at', [$from, $to])
                    ->count();

                $lostCount = (int) (clone $base)
                    ->where('status', CRMNegotiationStatus::LOST->value)
                    ->whereBetween('closed_at', [$from, $to])
                    ->count();

                $lossReasons = DB::table('crm_negotiations as n')
                    ->join('crm_reason_losses as r', 'r.id', '=', 'n.crm_reason_loss_id')
                    ->where('n.tenant_id', $tenantId)
                    ->where('r.tenant_id', $tenantId)
                    ->whereNull('n.deleted_at')
                    ->where('n.status', CRMNegotiationStatus::LOST->value)
                    ->whereNotNull('n.crm_reason_loss_id')
                    ->whereBetween('n.closed_at', [$from, $to])
                    ->selectRaw('r.name as name, COUNT(n.id) as count')
                    ->groupBy('r.name')
                    ->orderByDesc('count')
                    ->limit(self::TOP_LIMIT)
                    ->get();

                return [
                    'by_status' => [
                        'open' => $openCount,
                        'won' => $wonCount,
                        'lost' => $lostCount,
                    ],
                    'top_loss_reasons' => $lossReasons
                        ->map(fn ($row) => ['name' => (string) $row->name, 'count' => (int) $row->count])
                        ->all(),
                ];
            }
        );
    }
}
