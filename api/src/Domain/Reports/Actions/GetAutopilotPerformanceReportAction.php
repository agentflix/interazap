<?php

declare(strict_types=1);

namespace Domain\Reports\Actions;

use Domain\Reports\DTOs\ReportsFilterDTO;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Relatório de Performance de Automações (Autopilot).
 *
 * Retorna métricas por playbook, por trigger type, aprovações pendentes e evolução diária.
 */
final class GetAutopilotPerformanceReportAction extends AbstractReportAction
{
    /**
     * Executa o relatório de performance de automações.
     *
     * @return array<string, mixed>
     */
    public function execute(ReportsFilterDTO $dto): array
    {
        $cacheKey = sprintf(
            'reports:%s:autopilot:%s',
            $dto->tenantId,
            md5(serialize([
                $dto->startDate->toDateString(),
                $dto->endDate->toDateString(),
            ]))
        );

        /** @var array<string, mixed> */
        return Cache::remember($cacheKey, $this->cacheTtl(), function () use ($dto): array {
            return [
                'by_playbook' => $this->getByPlaybook($dto),
                'by_trigger_type' => $this->getByTriggerType($dto),
                'daily_runs' => $this->getDailyRuns($dto),
            ];
        });
    }

    /**
     * Métricas por playbook.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getByPlaybook(ReportsFilterDTO $dto): array
    {
        return DB::table('ai_autopilot_runs as r')
            ->where('r.tenant_id', $dto->tenantId)
            ->whereBetween('r.created_at', [$dto->startDate, $dto->endDate])
            ->selectRaw("
                'v2-runtime' as playbook_name,
                COUNT(r.id) as total_runs,
                COUNT(CASE WHEN r.status = 'completed' THEN 1 END) as completed,
                COUNT(CASE WHEN r.status = 'failed' THEN 1 END) as failed,
                AVG(CASE WHEN r.completed_at IS NOT NULL AND r.started_at IS NOT NULL
                    THEN EXTRACT(EPOCH FROM (r.completed_at - r.started_at)) * 1000
                END) as avg_duration_ms
            ")
            ->orderByDesc('total_runs')
            ->get()
            ->map(function (object $row): array {
                $total = max((int) $row->total_runs, 1);

                return [
                    'name' => (string) $row->playbook_name,
                    'total_runs' => (int) $row->total_runs,
                    'success_rate' => round(((int) $row->completed / $total) * 100, 2),
                    'avg_duration_ms' => round((float) ($row->avg_duration_ms ?? 0), 1),
                ];
            })->all();
    }

    /**
     * Contagem de execuções por trigger type.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getByTriggerType(ReportsFilterDTO $dto): array
    {
        return DB::table('ai_autopilot_runs as r')
            ->where('r.tenant_id', $dto->tenantId)
            ->whereBetween('r.created_at', [$dto->startDate, $dto->endDate])
            ->selectRaw("COALESCE(r.classifier_result, 'unknown') as trigger_type, COUNT(r.id) as count")
            ->groupBy('r.classifier_result')
            ->orderByDesc('count')
            ->get()
            ->map(fn (object $row): array => [
                'trigger_type' => (string) $row->trigger_type,
                'count' => (int) $row->count,
            ])->all();
    }

    /**
     * Evolução diária de execuções.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getDailyRuns(ReportsFilterDTO $dto): array
    {
        return DB::table('ai_autopilot_runs as r')
            ->where('r.tenant_id', $dto->tenantId)
            ->whereBetween('r.created_at', [$dto->startDate, $dto->endDate])
            ->selectRaw("
                DATE_TRUNC('day', r.created_at) as period,
                COUNT(r.id) as total,
                COUNT(CASE WHEN r.status = 'completed' THEN 1 END) as completed,
                COUNT(CASE WHEN r.status = 'failed' THEN 1 END) as failed
            ")
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->map(fn (object $row): array => [
                'period' => (string) $row->period,
                'total' => (int) $row->total,
                'completed' => (int) $row->completed,
                'failed' => (int) $row->failed,
            ])->all();
    }
}
