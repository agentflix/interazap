<?php

declare(strict_types=1);

namespace Domain\Dashboard\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Builds the recent activities list for the dashboard.
 */
final class GetRecentActivitiesAction
{
    private const RECENT_LIMIT = 10;

    private const ICON_HANDSHAKE = 'lucideHandshake';

    private const ICON_HEADSET = 'lucideHeadset';

    private const ICON_FILE_TEXT = 'lucideFileText';

    /**
     * @return array<int, array<string, string>>
     */
    public function execute(string $tenantId, Carbon $from, Carbon $to): array
    {
        return Cache::remember(
            "dashboard:{$tenantId}:{$from->toDateString()}:{$to->toDateString()}:".class_basename(self::class),
            120,
            function () use ($tenantId, $from, $to): array {
                $negotiations = DB::table('crm_negotiations')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at')
                    ->whereBetween('created_at', [$from, $to])
                    ->selectRaw("'negotiation' as type")
                    ->selectRaw('title as title')
                    ->selectRaw("CONCAT('Value: ', amount) as description")
                    ->selectRaw('created_at as created_at')
                    ->selectRaw('? as icon', [self::ICON_HANDSHAKE]);

                $tickets = DB::table('chat_tickets')
                    ->leftJoin('chat_tickets_extended as cte', 'cte.ticket_id', '=', 'chat_tickets.id')
                    ->where('chat_tickets.tenant_id', $tenantId)
                    ->whereNull('chat_tickets.deleted_at')
                    ->whereBetween('chat_tickets.created_at', [$from, $to])
                    ->selectRaw("'ticket' as type")
                    ->selectRaw("COALESCE(cte.subject, CONCAT('Ticket ', chat_tickets.protocol)) as title")
                    ->selectRaw("CONCAT('Channel: ', chat_tickets.channel) as description")
                    ->selectRaw('chat_tickets.created_at as created_at')
                    ->selectRaw('? as icon', [self::ICON_HEADSET]);

                $proposals = DB::table('crm_proposals')
                    ->where('tenant_id', $tenantId)
                    ->whereBetween('created_at', [$from, $to])
                    ->selectRaw("'proposal' as type")
                    ->selectRaw('title as title')
                    ->selectRaw("CONCAT('Status: ', status) as description")
                    ->selectRaw('created_at as created_at')
                    ->selectRaw('? as icon', [self::ICON_FILE_TEXT]);

                $union = $negotiations
                    ->unionAll($tickets)
                    ->unionAll($proposals);

                $rows = DB::query()
                    ->fromSub($union, 'activities')
                    ->orderByDesc('created_at')
                    ->limit(self::RECENT_LIMIT)
                    ->get();

                return $rows
                    ->map(fn ($row) => [
                        'type' => (string) $row->type,
                        'title' => (string) $row->title,
                        'description' => (string) $row->description,
                        'created_at' => Carbon::parse($row->created_at)->toISOString(),
                        'icon' => (string) $row->icon,
                    ])
                    ->all();
            }
        );
    }
}
