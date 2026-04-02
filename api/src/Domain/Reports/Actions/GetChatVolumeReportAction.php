<?php

declare(strict_types=1);

namespace Domain\Reports\Actions;

use Domain\Reports\DTOs\ReportsFilterDTO;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Relatório de Volume de Atendimento.
 *
 * Retorna total de tickets, heatmap hora×dia, distribuição por canal/sentimento, taxas de auto-resolução.
 */
final class GetChatVolumeReportAction extends AbstractReportAction
{
    /**
     * Executa o relatório de volume de atendimento.
     *
     * @return array<string, mixed>
     */
    public function execute(ReportsFilterDTO $dto): array
    {
        $cacheKey = sprintf(
            'reports:%s:chat_volume:%s',
            $dto->tenantId,
            md5(serialize([
                $dto->startDate->toDateString(),
                $dto->endDate->toDateString(),
                $dto->channel,
                $dto->instanceId,
            ]))
        );

        /** @var array<string, mixed> */
        return Cache::remember($cacheKey, $this->cacheTtl(), function () use ($dto): array {
            return [
                'summary' => $this->getSummary($dto),
                'by_channel' => $this->getByChannel($dto),
                'by_instance' => $this->getByInstance($dto),
                'heatmap' => $this->getHeatmap($dto),
                'by_sentiment' => $this->getBySentiment($dto),
                'auto_resolution' => $this->getAutoResolution($dto),
            ];
        });
    }

    /**
     * Totais e média diária.
     *
     * @return array<string, mixed>
     */
    private function getSummary(ReportsFilterDTO $dto): array
    {
        $query = $this->baseQuery($dto);

        $row = $query
            ->selectRaw('COUNT(t.id) as total, COUNT(DISTINCT DATE(t.created_at)) as days')
            ->first();

        $total = (int) ($row->total ?? 0);
        $days = max((int) ($row->days ?? 1), 1);

        return [
            'total_tickets' => $total,
            'avg_daily' => round($total / $days, 1),
            'days_in_period' => $days,
        ];
    }

    /**
     * Volume por canal.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getByChannel(ReportsFilterDTO $dto): array
    {
        return $this->baseQuery($dto)
            ->selectRaw('t.channel, COUNT(t.id) as count')
            ->groupBy('t.channel')
            ->orderByDesc('count')
            ->get()
            ->map(fn (object $row): array => [
                'channel' => (string) $row->channel,
                'count' => (int) $row->count,
            ])->all();
    }

    /**
     * Volume por instância.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getByInstance(ReportsFilterDTO $dto): array
    {
        return $this->baseQuery($dto)
            ->whereNotNull('t.instance_id')
            ->selectRaw('t.instance_id, COUNT(t.id) as count')
            ->groupBy('t.instance_id')
            ->orderByDesc('count')
            ->get()
            ->map(fn (object $row): array => [
                'instance_id' => (string) $row->instance_id,
                'count' => (int) $row->count,
            ])->all();
    }

    /**
     * Heatmap 7×24 (dia da semana × hora).
     *
     * @return array<int, array<string, mixed>>
     */
    private function getHeatmap(ReportsFilterDTO $dto): array
    {
        return $this->baseQuery($dto)
            ->selectRaw('EXTRACT(DOW FROM t.created_at) as day_of_week, EXTRACT(HOUR FROM t.created_at) as hour, COUNT(t.id) as count')
            ->groupBy('day_of_week', 'hour')
            ->orderBy('day_of_week')
            ->orderBy('hour')
            ->get()
            ->map(fn (object $row): array => [
                'day_of_week' => (int) $row->day_of_week,
                'hour' => (int) $row->hour,
                'count' => (int) $row->count,
            ])->all();
    }

    /**
     * Distribuição por sentimento.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getBySentiment(ReportsFilterDTO $dto): array
    {
        return $this->baseQuery($dto)
            ->whereNotNull('t.sentiment')
            ->selectRaw('t.sentiment, COUNT(t.id) as count')
            ->groupBy('t.sentiment')
            ->orderByDesc('count')
            ->get()
            ->map(fn (object $row): array => [
                'sentiment' => (string) $row->sentiment,
                'count' => (int) $row->count,
            ])->all();
    }

    /**
     * Tickets com takeover humano e taxa de auto-resolução.
     *
     * @return array<string, mixed>
     */
    private function getAutoResolution(ReportsFilterDTO $dto): array
    {
        $query = $this->baseQuery($dto);

        $row = $query
            ->leftJoin('chat_tickets_extended as cte', 'cte.ticket_id', '=', 't.id')
            ->selectRaw('
                COUNT(t.id) as total,
                COUNT(CASE WHEN cte.human_takeover_at IS NOT NULL THEN 1 END) as human_takeover,
                COUNT(CASE WHEN t.closed_at IS NOT NULL AND cte.human_takeover_at IS NULL THEN 1 END) as auto_resolved
            ')
            ->first();

        $total = max((int) ($row->total ?? 0), 1);

        return [
            'total' => (int) ($row->total ?? 0),
            'human_takeover' => (int) ($row->human_takeover ?? 0),
            'auto_resolved' => (int) ($row->auto_resolved ?? 0),
            'auto_resolution_rate' => round(((int) ($row->auto_resolved ?? 0) / $total) * 100, 2),
        ];
    }

    /**
     * Query base para chat_tickets.
     */
    private function baseQuery(ReportsFilterDTO $dto): \Illuminate\Database\Query\Builder
    {
        $query = DB::table('chat_tickets as t')
            ->where('t.tenant_id', $dto->tenantId)
            ->whereNull('t.deleted_at')
            ->whereBetween('t.created_at', [$dto->startDate, $dto->endDate]);

        if ($dto->channel !== null) {
            $query->where('t.channel', $dto->channel);
        }

        if ($dto->instanceId !== null) {
            $query->where('t.instance_id', $dto->instanceId);
        }

        return $query;
    }
}
