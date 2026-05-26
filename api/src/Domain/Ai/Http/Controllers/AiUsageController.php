<?php

declare(strict_types=1);

namespace Domain\Ai\Http\Controllers;

use Domain\Ai\Actions\AiUsageActions;
use Domain\Ai\Actions\AiUsageSummaryAction;
use Domain\Ai\Actions\GetMediaTranscriptionReportAction;
use Domain\Ai\Http\Resources\UsageAgentResource;
use Domain\Ai\Http\Resources\UsageDailyResource;
use Domain\Ai\Http\Resources\UsageSummaryResource;
use Domain\Shared\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Controller para endpoints de análise de consumo de IA do módulo de IA.
 *
 * Fornece métricas de uso, custos e tendências do consumo de IA do tenant.
 */
final class AiUsageController extends BaseController
{
    public function __construct(
        private readonly AiUsageActions $actions,
        private readonly AiUsageSummaryAction $summaryAction,
        private readonly GetMediaTranscriptionReportAction $transcriptionReportAction,
    ) {}

    /**
     * Retorna o resumo de consumo do período atual.
     *
     * @param  Request  $request  Requisição HTTP.
     * @return JsonResponse Totais de requisições, tokens, custo e tendência.
     */
    public function summary(Request $request): JsonResponse
    {
        $this->authorize('ai.autopilots.manage');

        $tenantId = $this->tenantId($request);
        $summary = $this->summaryAction->execute($tenantId);

        return response()->json([
            'success' => true,
            'data' => new UsageSummaryResource($summary),
        ]);
    }

    /**
     * Retorna o consumo diário dos últimos 30 dias.
     *
     * @param  Request  $request  Requisição HTTP com parâmetro 'days' opcional (máx. 90).
     * @return AnonymousResourceCollection Série temporal de consumo diário.
     */
    public function daily(Request $request): AnonymousResourceCollection
    {
        $this->authorize('ai.autopilots.manage');

        $tenantId = $this->tenantId($request);
        $days = min((int) $request->input('days', 30), 90);

        $dailyStats = $this->actions->daily($tenantId, $days);

        return UsageDailyResource::collection($dailyStats);
    }

    /**
     * Lista os agentes de IA com maior consumo.
     *
     * @param  Request  $request  Requisição HTTP com parâmetro 'limit' opcional (máx. 50).
     * @return AnonymousResourceCollection Agentes ordenados por consumo decrescente.
     */
    public function topAgents(Request $request): AnonymousResourceCollection
    {
        $this->authorize('ai.autopilots.manage');

        $tenantId = $this->tenantId($request);
        $limit = min((int) $request->input('limit', 10), 50);

        $topAgents = $this->actions->topAgents($tenantId, $limit);

        return UsageAgentResource::collection($topAgents);
    }

    /**
     * Retorna o histórico mensal de consumo.
     *
     * @param  Request  $request  Requisição HTTP com parâmetro 'months' opcional (máx. 12).
     * @return JsonResponse Série temporal de consumo mensal.
     */
    public function monthlyHistory(Request $request): JsonResponse
    {
        $this->authorize('ai.autopilots.manage');

        $tenantId = $this->tenantId($request);
        $months = min((int) $request->input('months', 6), 12);
        $monthlyStats = $this->actions->monthlyHistory($tenantId, $months);

        return response()->json([
            'success' => true,
            'data' => $monthlyStats,
        ]);
    }

    /**
     * Retorna o relatório de consumo de transcrição de mídia.
     *
     * @param  Request  $request  Requisição HTTP com start_date e end_date opcionais.
     * @return JsonResponse Relatório de transcrições no período.
     */
    public function transcriptionReport(Request $request): JsonResponse
    {
        $this->authorize('ai.autopilots.manage');

        $tenantId = $this->tenantId($request);
        $startDate = $request->input('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->input('end_date', now()->format('Y-m-d'));

        $report = $this->transcriptionReportAction->execute(
            $tenantId,
            (string) $startDate,
            (string) $endDate,
        );

        return $this->success(['data' => $report, 'meta' => []]);
    }
}
