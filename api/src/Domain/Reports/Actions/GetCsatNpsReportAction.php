<?php

declare(strict_types=1);

namespace Domain\Reports\Actions;

use Domain\Reports\DTOs\ReportsFilterDTO;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Relatório de CSAT e NPS.
 *
 * Retorna NPS score, CSAT médio, distribuição de ratings, evolução temporal e comentários negativos.
 */
final class GetCsatNpsReportAction extends AbstractReportAction
{
    /**
     * Executa o relatório de CSAT/NPS.
     *
     * @return array<string, mixed>
     */
    public function execute(ReportsFilterDTO $dto): array
    {
        $cacheKey = sprintf(
            'reports:%s:csat:%s',
            $dto->tenantId,
            md5(serialize([
                $dto->startDate->toDateString(),
                $dto->endDate->toDateString(),
                $dto->channel,
                $dto->userId,
            ]))
        );

        /** @var array<string, mixed> */
        return Cache::remember($cacheKey, $this->cacheTtl(), function () use ($dto): array {
            return [
                'summary' => $this->getSummary($dto),
                'distribution' => $this->getDistribution($dto),
                'timeline' => $this->getTimeline($dto),
                'by_agent' => $this->getByAgent($dto),
                'by_channel' => $this->getByChannel($dto),
                'negative_comments' => $this->getNegativeComments($dto),
            ];
        });
    }

    /**
     * NPS Score e CSAT médio.
     *
     * @return array<string, mixed>
     */
    private function getSummary(ReportsFilterDTO $dto): array
    {
        $query = $this->baseQuery($dto);

        $row = $query
            ->selectRaw('
                COUNT(e.id) as total_evaluations,
                AVG(e.rating) as csat_avg,
                COUNT(CASE WHEN e.rating >= 4 THEN 1 END) as promoters,
                COUNT(CASE WHEN e.rating = 3 THEN 1 END) as passives,
                COUNT(CASE WHEN e.rating <= 2 THEN 1 END) as detractors
            ')
            ->first();

        $totalEligible = DB::table('chat_ticket_evaluations as e')
            ->join('chat_tickets as t', 't.id', '=', 'e.ticket_id')
            ->where('e.tenant_id', $dto->tenantId)
            ->whereBetween('e.created_at', [$dto->startDate, $dto->endDate])
            ->count();

        $submitted = (int) ($row->total_evaluations ?? 0);
        $total = $submitted > 0 ? $submitted : 1;
        $promoterPct = ((int) ($row->promoters ?? 0) / $total) * 100;
        $detractorPct = ((int) ($row->detractors ?? 0) / $total) * 100;

        return [
            'nps_score' => round($promoterPct - $detractorPct, 1),
            'csat_avg' => round((float) ($row->csat_avg ?? 0), 2),
            'total_evaluations' => $submitted,
            'promoters' => (int) ($row->promoters ?? 0),
            'passives' => (int) ($row->passives ?? 0),
            'detractors' => (int) ($row->detractors ?? 0),
            'response_rate' => $totalEligible > 0
                ? round(($submitted / $totalEligible) * 100, 2)
                : 0.0,
        ];
    }

    /**
     * Distribuição de ratings 1-5.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getDistribution(ReportsFilterDTO $dto): array
    {
        $rows = $this->baseQuery($dto)
            ->selectRaw('e.rating, COUNT(e.id) as count')
            ->groupBy('e.rating')
            ->orderBy('e.rating')
            ->get();

        return $rows->map(fn (object $row): array => [
            'rating' => (int) $row->rating,
            'count' => (int) $row->count,
        ])->all();
    }

    /**
     * Evolução temporal do CSAT.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getTimeline(ReportsFilterDTO $dto): array
    {
        $allowed = ['day', 'week', 'month'];
        $granularity = in_array($dto->granularity, $allowed, true)
            ? $dto->granularity
            : 'day';

        return $this->baseQuery($dto)
            ->selectRaw("DATE_TRUNC('{$granularity}', e.submitted_at) as period, AVG(e.rating) as csat_avg, COUNT(e.id) as count")
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->map(fn (object $row): array => [
                'period' => (string) $row->period,
                'csat_avg' => round((float) $row->csat_avg, 2),
                'count' => (int) $row->count,
            ])->all();
    }

    /**
     * CSAT por agente.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getByAgent(ReportsFilterDTO $dto): array
    {
        return $this->baseQuery($dto)
            ->join('auth_users as u', 'u.id', '=', 't.assigned_to')
            ->selectRaw('u.name as agent_name, AVG(e.rating) as csat_avg, COUNT(e.id) as count')
            ->groupBy('u.id', 'u.name')
            ->orderByDesc('csat_avg')
            ->get()
            ->map(fn (object $row): array => [
                'agent_name' => (string) $row->agent_name,
                'csat_avg' => round((float) $row->csat_avg, 2),
                'count' => (int) $row->count,
            ])->all();
    }

    /**
     * CSAT por canal.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getByChannel(ReportsFilterDTO $dto): array
    {
        return $this->baseQuery($dto)
            ->selectRaw('t.channel, AVG(e.rating) as csat_avg, COUNT(e.id) as count')
            ->groupBy('t.channel')
            ->orderByDesc('csat_avg')
            ->get()
            ->map(fn (object $row): array => [
                'channel' => (string) $row->channel,
                'csat_avg' => round((float) $row->csat_avg, 2),
                'count' => (int) $row->count,
            ])->all();
    }

    /**
     * Comentários negativos (rating <= 2).
     *
     * @return array<int, array<string, mixed>>
     */
    private function getNegativeComments(ReportsFilterDTO $dto): array
    {
        return $this->baseQuery($dto)
            ->where('e.rating', '<=', 2)
            ->whereNotNull('e.comment')
            ->where('e.comment', '!=', '')
            ->select(['e.rating', 'e.comment', 'e.submitted_at', 't.protocol', 't.channel'])
            ->orderByDesc('e.submitted_at')
            ->limit(50)
            ->get()
            ->map(fn (object $row): array => [
                'rating' => (int) $row->rating,
                'comment' => (string) $row->comment,
                'submitted_at' => (string) $row->submitted_at,
                'protocol' => (string) ($row->protocol ?? ''),
                'channel' => (string) $row->channel,
            ])->all();
    }

    /**
     * Query base para avaliações de tickets.
     */
    private function baseQuery(ReportsFilterDTO $dto): \Illuminate\Database\Query\Builder
    {
        $query = DB::table('chat_ticket_evaluations as e')
            ->join('chat_tickets as t', 't.id', '=', 'e.ticket_id')
            ->where('e.tenant_id', $dto->tenantId)
            ->whereNotNull('e.submitted_at')
            ->where('e.rating', '>', 0)
            ->whereBetween('e.created_at', [$dto->startDate, $dto->endDate]);

        if ($dto->channel !== null) {
            $query->where('t.channel', $dto->channel);
        }

        if ($dto->userId !== null) {
            $query->where('t.assigned_to', $dto->userId);
        }

        return $query;
    }
}
