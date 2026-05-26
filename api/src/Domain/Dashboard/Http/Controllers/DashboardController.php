<?php

declare(strict_types=1);

namespace Domain\Dashboard\Http\Controllers;

use Domain\Dashboard\Actions\GetCsatStatsAction;
use Domain\Dashboard\Actions\GetDashboardSummaryAction;
use Domain\Dashboard\Actions\GetNegotiationStatsAction;
use Domain\Dashboard\Actions\GetRecentActivitiesAction;
use Domain\Dashboard\Actions\GetRevenueChartAction;
use Domain\Dashboard\Actions\GetSalesFunnelAction;
use Domain\Dashboard\Actions\GetTicketStatsAction;
use Domain\Dashboard\Http\Requests\DashboardFilterRequest;
use Domain\Shared\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;

/**
 * Controlador do dashboard que consolida métricas de CRM e atendimento.
 *
 * Agrega dados de summary, funil, receita, negociações, tickets, CSAT e
 * atividades recentes em uma única resposta para o frontend.
 *
 * @see GetDashboardSummaryAction
 * @see GetSalesFunnelAction
 * @see GetRevenueChartAction
 * @see GetNegotiationStatsAction
 * @see GetTicketStatsAction
 * @see GetCsatStatsAction
 * @see GetRecentActivitiesAction
 */
final class DashboardController extends BaseController
{
    /**
     * @param  GetDashboardSummaryAction  $summaryAction  Ação de resumo.
     * @param  GetSalesFunnelAction  $salesFunnelAction  Ação de funil de vendas.
     * @param  GetRevenueChartAction  $revenueChartAction  Ação de gráfico de receita.
     * @param  GetNegotiationStatsAction  $negotiationStatsAction  Ação de estatísticas de negociações.
     * @param  GetTicketStatsAction  $ticketStatsAction  Ação de estatísticas de tickets.
     * @param  GetCsatStatsAction  $csatStatsAction  Ação de estatísticas CSAT.
     * @param  GetRecentActivitiesAction  $recentActivitiesAction  Ação de atividades recentes.
     */
    public function __construct(
        private readonly GetDashboardSummaryAction $summaryAction,
        private readonly GetSalesFunnelAction $salesFunnelAction,
        private readonly GetRevenueChartAction $revenueChartAction,
        private readonly GetNegotiationStatsAction $negotiationStatsAction,
        private readonly GetTicketStatsAction $ticketStatsAction,
        private readonly GetCsatStatsAction $csatStatsAction,
        private readonly GetRecentActivitiesAction $recentActivitiesAction,
    ) {}

    /**
     * Obter dados consolidados do dashboard.
     *
     * @param  DashboardFilterRequest  $request  Requisição com filtros de período.
     * @return JsonResponse Dados do dashboard (resumo, funil, receita, etc).
     */
    public function index(DashboardFilterRequest $request): JsonResponse
    {
        $this->authorize('dashboard.view');

        $tenantId = $this->tenantId($request);
        $period = $request->period();

        return $this->success([
            'summary' => $this->summaryAction->execute($tenantId, $period['from'], $period['to']),
            'funnel' => $this->salesFunnelAction->execute($tenantId, $period['from'], $period['to']),
            'revenue' => $this->revenueChartAction->execute($tenantId, $period['from'], $period['to']),
            'negotiations' => $this->negotiationStatsAction->execute($tenantId, $period['from'], $period['to']),
            'tickets' => $this->ticketStatsAction->execute($tenantId, $period['from'], $period['to']),
            'csat' => $this->csatStatsAction->execute($tenantId, $period['from'], $period['to']),
            'activities' => $this->recentActivitiesAction->execute($tenantId, $period['from'], $period['to']),
        ]);
    }
}
