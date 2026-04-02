<?php

declare(strict_types=1);

namespace Domain\Reports\Actions;

use Domain\Reports\DTOs\ReportsFilterDTO;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Relatório de Motivos de Perda.
 *
 * Retorna análise detalhada dos motivos de perda com cruzamentos por etapa e responsável.
 */
final class GetLossReasonReportAction extends AbstractReportAction
{
    /**
     * Executa o relatório de motivos de perda.
     *
     * @return array<string, mixed>
     */
    public function execute(ReportsFilterDTO $dto): array
    {
        $cacheKey = sprintf(
            'reports:%s:loss_reason:%s',
            $dto->tenantId,
            md5(serialize([
                $dto->startDate->toDateString(),
                $dto->endDate->toDateString(),
                $dto->funnelId,
                $dto->userId,
                $dto->reasonLossId,
                $dto->granularity,
            ]))
        );

        /** @var array<string, mixed> */
        return Cache::remember($cacheKey, $this->cacheTtl(), function () use ($dto): array {
            return [
                'reasons' => $this->getReasons($dto),
                'by_step' => $this->getByStep($dto),
                'by_user' => $this->getByUser($dto),
                'timeline' => $this->getTimeline($dto),
            ];
        });
    }

    /**
     * Motivos de perda com contagem, valor e percentual.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getReasons(ReportsFilterDTO $dto): array
    {
        $query = DB::table('crm_negotiations as n')
            ->join('crm_reason_losses as r', 'r.id', '=', 'n.crm_reason_loss_id')
            ->where('n.tenant_id', $dto->tenantId)
            ->where('n.status', 'lost')
            ->whereNull('n.deleted_at')
            ->whereBetween('n.created_at', [$dto->startDate, $dto->endDate]);

        if ($dto->funnelId !== null) {
            $query->where('n.crm_negotiation_funnel_id', $dto->funnelId);
        }

        if ($dto->userId !== null) {
            $query->where('n.auth_user_id', $dto->userId);
        }

        if ($dto->reasonLossId !== null) {
            $query->where('n.crm_reason_loss_id', $dto->reasonLossId);
        }

        $rows = $query
            ->selectRaw('r.name as reason_name, COUNT(n.id) as count, COALESCE(SUM(n.amount), 0) as total_amount')
            ->groupBy('r.id', 'r.name')
            ->orderByDesc('count')
            ->get();

        $total = $rows->sum('count');

        return $rows->map(fn (object $row): array => [
            'reason_name' => (string) $row->reason_name,
            'count' => (int) $row->count,
            'total_amount' => round((float) $row->total_amount, 2),
            'percentage' => $total > 0 ? round(((int) $row->count / $total) * 100, 2) : 0.0,
        ])->all();
    }

    /**
     * Cruzamento motivo × etapa do funil.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getByStep(ReportsFilterDTO $dto): array
    {
        $query = DB::table('crm_negotiations as n')
            ->join('crm_reason_losses as r', 'r.id', '=', 'n.crm_reason_loss_id')
            ->join('crm_negotiation_funnel_steps as s', 's.id', '=', 'n.crm_negotiation_funnel_step_id')
            ->where('n.tenant_id', $dto->tenantId)
            ->where('n.status', 'lost')
            ->whereNull('n.deleted_at')
            ->whereBetween('n.created_at', [$dto->startDate, $dto->endDate]);

        if ($dto->funnelId !== null) {
            $query->where('n.crm_negotiation_funnel_id', $dto->funnelId);
        }

        return $query
            ->selectRaw('r.name as reason_name, s.name as step_name, COUNT(n.id) as count, COALESCE(SUM(n.amount), 0) as total_amount')
            ->groupBy('r.name', 's.name')
            ->orderByDesc('count')
            ->get()
            ->map(fn (object $row): array => [
                'reason_name' => (string) $row->reason_name,
                'step_name' => (string) $row->step_name,
                'count' => (int) $row->count,
                'total_amount' => round((float) $row->total_amount, 2),
            ])->all();
    }

    /**
     * Cruzamento motivo × responsável.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getByUser(ReportsFilterDTO $dto): array
    {
        $query = DB::table('crm_negotiations as n')
            ->join('crm_reason_losses as r', 'r.id', '=', 'n.crm_reason_loss_id')
            ->join('auth_users as u', 'u.id', '=', 'n.auth_user_id')
            ->where('n.tenant_id', $dto->tenantId)
            ->where('n.status', 'lost')
            ->whereNull('n.deleted_at')
            ->whereBetween('n.created_at', [$dto->startDate, $dto->endDate]);

        if ($dto->funnelId !== null) {
            $query->where('n.crm_negotiation_funnel_id', $dto->funnelId);
        }

        if ($dto->userId !== null) {
            $query->where('n.auth_user_id', $dto->userId);
        }

        return $query
            ->selectRaw('r.name as reason_name, u.name as user_name, COUNT(n.id) as count, COALESCE(SUM(n.amount), 0) as total_amount')
            ->groupBy('r.name', 'u.name')
            ->orderByDesc('count')
            ->get()
            ->map(fn (object $row): array => [
                'reason_name' => (string) $row->reason_name,
                'user_name' => (string) $row->user_name,
                'count' => (int) $row->count,
                'total_amount' => round((float) $row->total_amount, 2),
            ])->all();
    }

    /**
     * Evolução temporal dos motivos de perda.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getTimeline(ReportsFilterDTO $dto): array
    {
        $allowed = ['day', 'week', 'month'];
        $granularity = in_array($dto->granularity, $allowed, true)
            ? $dto->granularity
            : 'day';

        $query = DB::table('crm_negotiations as n')
            ->join('crm_reason_losses as r', 'r.id', '=', 'n.crm_reason_loss_id')
            ->where('n.tenant_id', $dto->tenantId)
            ->where('n.status', 'lost')
            ->whereNull('n.deleted_at')
            ->whereBetween('n.created_at', [$dto->startDate, $dto->endDate]);

        if ($dto->funnelId !== null) {
            $query->where('n.crm_negotiation_funnel_id', $dto->funnelId);
        }

        return $query
            ->selectRaw("DATE_TRUNC('{$granularity}', n.created_at) as period, r.name as reason_name, COUNT(n.id) as count")
            ->groupBy('period', 'r.name')
            ->orderBy('period')
            ->get()
            ->map(fn (object $row): array => [
                'period' => (string) $row->period,
                'reason_name' => (string) $row->reason_name,
                'count' => (int) $row->count,
            ])->all();
    }
}
