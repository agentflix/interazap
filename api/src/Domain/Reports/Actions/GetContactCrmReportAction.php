<?php

declare(strict_types=1);

namespace Domain\Reports\Actions;

use Domain\Reports\DTOs\ReportsFilterDTO;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Relatório de Contatos CRM.
 *
 * Retorna totais ativos/inativos, novos no período, leads frios, crescimento mensal e tags.
 */
final class GetContactCrmReportAction extends AbstractReportAction
{
    /**
     * Executa o relatório de contatos CRM.
     *
     * @return array<string, mixed>
     */
    public function execute(ReportsFilterDTO $dto): array
    {
        $cacheKey = sprintf(
            'reports:%s:contacts:%s',
            $dto->tenantId,
            md5(serialize([
                $dto->startDate->toDateString(),
                $dto->endDate->toDateString(),
            ]))
        );

        /** @var array<string, mixed> */
        return Cache::remember($cacheKey, $this->cacheTtl(), function () use ($dto): array {
            return [
                'summary' => $this->getSummary($dto),
                'by_company' => $this->getByCompany($dto),
                'cold_leads' => $this->getColdLeads($dto),
                'monthly_growth' => $this->getMonthlyGrowth($dto),
                'top_tags' => $this->getTopTags($dto),
            ];
        });
    }

    /**
     * Totais de contatos.
     *
     * @return array<string, int>
     */
    private function getSummary(ReportsFilterDTO $dto): array
    {
        $total = DB::table('crm_contacts')
            ->where('tenant_id', $dto->tenantId)
            ->whereNull('deleted_at')
            ->count();

        $active = DB::table('crm_contacts')
            ->where('tenant_id', $dto->tenantId)
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->count();

        $newInPeriod = DB::table('crm_contacts')
            ->where('tenant_id', $dto->tenantId)
            ->whereNull('deleted_at')
            ->whereBetween('created_at', [$dto->startDate, $dto->endDate])
            ->count();

        return [
            'total' => $total,
            'active' => $active,
            'inactive' => $total - $active,
            'new_in_period' => $newInPeriod,
        ];
    }

    /**
     * Contatos por empresa.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getByCompany(ReportsFilterDTO $dto): array
    {
        return DB::table('crm_contacts as c')
            ->join('crm_companies as co', 'co.id', '=', 'c.crm_company_id')
            ->where('c.tenant_id', $dto->tenantId)
            ->whereNull('c.deleted_at')
            ->selectRaw('co.name as company_name, COUNT(c.id) as contact_count')
            ->groupBy('co.id', 'co.name')
            ->orderByDesc('contact_count')
            ->limit(20)
            ->get()
            ->map(fn (object $row): array => [
                'company_name' => (string) $row->company_name,
                'contact_count' => (int) $row->contact_count,
            ])->all();
    }

    /**
     * Leads frios (sem negociação vinculada).
     *
     * @return array<string, int>
     */
    private function getColdLeads(ReportsFilterDTO $dto): array
    {
        $totalContacts = DB::table('crm_contacts')
            ->where('tenant_id', $dto->tenantId)
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->count();

        $withNegotiation = DB::table('crm_contacts as c')
            ->where('c.tenant_id', $dto->tenantId)
            ->whereNull('c.deleted_at')
            ->where('c.is_active', true)
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('crm_negotiations as n')
                    ->whereColumn('n.crm_contact_id', 'c.id')
                    ->whereNull('n.deleted_at');
            })
            ->count();

        $noChat30 = DB::table('crm_contacts as c')
            ->where('c.tenant_id', $dto->tenantId)
            ->whereNull('c.deleted_at')
            ->where('c.is_active', true)
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('chat_tickets as t')
                    ->whereColumn('t.contact_id', 'c.id')
                    ->where('t.created_at', '>=', now()->subDays(30));
            })
            ->count();

        return [
            'no_negotiation' => $totalContacts - $withNegotiation,
            'no_chat_30_days' => $noChat30,
        ];
    }

    /**
     * Crescimento mês a mês.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getMonthlyGrowth(ReportsFilterDTO $dto): array
    {
        return DB::table('crm_contacts')
            ->where('tenant_id', $dto->tenantId)
            ->whereNull('deleted_at')
            ->whereBetween('created_at', [$dto->startDate, $dto->endDate])
            ->selectRaw("DATE_TRUNC('month', created_at) as period, COUNT(id) as new_contacts")
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->map(fn (object $row): array => [
                'period' => (string) $row->period,
                'new_contacts' => (int) $row->new_contacts,
            ])->all();
    }

    /**
     * Tags mais usadas.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getTopTags(ReportsFilterDTO $dto): array
    {
        return DB::table('crm_contact_tags as ct')
            ->join('crm_tags as t', 't.id', '=', 'ct.crm_tag_id')
            ->where('ct.tenant_id', $dto->tenantId)
            ->selectRaw('t.name as tag_name, COALESCE(t.color, \'#94a3b8\') as tag_color, COUNT(ct.id) as count')
            ->groupBy('t.id', 't.name', 't.color')
            ->orderByDesc('count')
            ->limit(20)
            ->get()
            ->map(fn (object $row): array => [
                'tag_name' => (string) $row->tag_name,
                'tag_color' => (string) $row->tag_color,
                'count' => (int) $row->count,
            ])->all();
    }
}
