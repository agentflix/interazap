<?php

declare(strict_types=1);

namespace Domain\Reports\Http\Controllers;

use Domain\Reports\Actions\GetAgentPerformanceReportAction;
use Domain\Reports\Actions\GetAiUsageCostReportAction;
use Domain\Reports\Actions\GetAutopilotPerformanceReportAction;
use Domain\Reports\Actions\GetBillingReportAction;
use Domain\Reports\Actions\GetChatVolumeReportAction;
use Domain\Reports\Actions\GetContactCrmReportAction;
use Domain\Reports\Actions\GetCsatNpsReportAction;
use Domain\Reports\Actions\GetLossReasonReportAction;
use Domain\Reports\Actions\GetProductPerformanceReportAction;
use Domain\Reports\Actions\GetRevenueSalesReportAction;
use Domain\Reports\Actions\GetSalesFunnelReportAction;
use Domain\Reports\Actions\GetSalespersonPerformanceReportAction;
use Domain\Reports\Actions\GetSlaResolutionReportAction;
use Domain\Reports\Actions\GetTeamActivityReportAction;
use Domain\Reports\DTOs\ReportsFilterDTO;
use Domain\Reports\Exports\ReportCsvExporter;
use Domain\Reports\Exports\ReportXlsxExporter;
use Domain\Reports\Http\Requests\ReportsExportRequest;
use Domain\Reports\Http\Requests\ReportsFilterRequest;
use Domain\Reports\Http\Resources\ReportResource;
use Domain\Reports\ReportActionRegistry;
use Domain\Shared\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Controlador HTTP para os relatórios analíticos do InteraZap.
 *
 * Cada método delega para uma action especializada e retorna os dados
 * encapsulados em ReportResource. O método export suporta CSV e XLSX
 * resolvendo a action via ReportActionRegistry.
 */
final class ReportsController extends BaseController
{
    /**
     * @param  GetSalesFunnelReportAction  $salesFunnelAction  Relatório de funil de vendas
     * @param  GetRevenueSalesReportAction  $revenueSalesAction  Relatório de receita e vendas
     * @param  GetSalespersonPerformanceReportAction  $salespersonAction  Relatório de performance de vendedores
     * @param  GetLossReasonReportAction  $lossReasonAction  Relatório de motivos de perda
     * @param  GetSlaResolutionReportAction  $slaResolutionAction  Relatório de SLA e resolução
     * @param  GetAgentPerformanceReportAction  $agentPerformanceAction  Relatório de performance de agentes
     * @param  GetCsatNpsReportAction  $csatNpsAction  Relatório de CSAT/NPS
     * @param  GetChatVolumeReportAction  $chatVolumeAction  Relatório de volume de atendimento
     * @param  GetAiUsageCostReportAction  $aiUsageCostAction  Relatório de uso e custo de IA
     * @param  GetBillingReportAction  $billingAction  Relatório de faturamento
     * @param  GetProductPerformanceReportAction  $productPerformanceAction  Relatório de performance de produtos
     * @param  GetAutopilotPerformanceReportAction  $autopilotAction  Relatório de performance de automações
     * @param  GetTeamActivityReportAction  $teamActivityAction  Relatório de atividade da equipe
     * @param  GetContactCrmReportAction  $contactCrmAction  Relatório de contatos CRM
     * @param  ReportActionRegistry  $registry  Registry para resolução de actions por tipo
     */
    public function __construct(
        private readonly GetSalesFunnelReportAction $salesFunnelAction,
        private readonly GetRevenueSalesReportAction $revenueSalesAction,
        private readonly GetSalespersonPerformanceReportAction $salespersonAction,
        private readonly GetLossReasonReportAction $lossReasonAction,
        private readonly GetSlaResolutionReportAction $slaResolutionAction,
        private readonly GetAgentPerformanceReportAction $agentPerformanceAction,
        private readonly GetCsatNpsReportAction $csatNpsAction,
        private readonly GetChatVolumeReportAction $chatVolumeAction,
        private readonly GetAiUsageCostReportAction $aiUsageCostAction,
        private readonly GetBillingReportAction $billingAction,
        private readonly GetProductPerformanceReportAction $productPerformanceAction,
        private readonly GetAutopilotPerformanceReportAction $autopilotAction,
        private readonly GetTeamActivityReportAction $teamActivityAction,
        private readonly GetContactCrmReportAction $contactCrmAction,
        private readonly ReportActionRegistry $registry,
    ) {}

    /**
     * Relatório de Funil de Vendas.
     */
    public function salesFunnel(ReportsFilterRequest $request): JsonResponse
    {
        Gate::authorize('reports.viewCrm');

        $dto = ReportsFilterDTO::fromRequest($request, $this->tenantId($request));
        $data = $this->salesFunnelAction->execute($dto);

        return $this->success(
            new ReportResource($data, $dto->startDate->toDateString(), $dto->endDate->toDateString()),
            'Relatório de funil de vendas'
        );
    }

    /**
     * Relatório de Receita e Vendas.
     */
    public function revenueSales(ReportsFilterRequest $request): JsonResponse
    {
        Gate::authorize('reports.viewCrm');

        $dto = ReportsFilterDTO::fromRequest($request, $this->tenantId($request));
        $data = $this->revenueSalesAction->execute($dto);

        return $this->success(
            new ReportResource($data, $dto->startDate->toDateString(), $dto->endDate->toDateString()),
            'Relatório de receita e vendas'
        );
    }

    /**
     * Relatório de Performance de Vendedores.
     */
    public function salespersonPerformance(ReportsFilterRequest $request): JsonResponse
    {
        Gate::authorize('reports.viewCrm');

        $dto = ReportsFilterDTO::fromRequest($request, $this->tenantId($request));
        $data = $this->salespersonAction->execute($dto);

        return $this->success(
            new ReportResource($data, $dto->startDate->toDateString(), $dto->endDate->toDateString()),
            'Relatório de performance de vendedores'
        );
    }

    /**
     * Relatório de Motivos de Perda.
     */
    public function lossReasons(ReportsFilterRequest $request): JsonResponse
    {
        Gate::authorize('reports.viewCrm');

        $dto = ReportsFilterDTO::fromRequest($request, $this->tenantId($request));
        $data = $this->lossReasonAction->execute($dto);

        return $this->success(
            new ReportResource($data, $dto->startDate->toDateString(), $dto->endDate->toDateString()),
            'Relatório de motivos de perda'
        );
    }

    /**
     * Relatório de SLA e Tempo de Resolução.
     */
    public function slaResolution(ReportsFilterRequest $request): JsonResponse
    {
        Gate::authorize('reports.viewChat');

        $dto = ReportsFilterDTO::fromRequest($request, $this->tenantId($request));
        $data = $this->slaResolutionAction->execute($dto);

        return $this->success(
            new ReportResource($data, $dto->startDate->toDateString(), $dto->endDate->toDateString()),
            'Relatório de SLA e resolução'
        );
    }

    /**
     * Relatório de Performance de Agentes.
     */
    public function agentPerformance(ReportsFilterRequest $request): JsonResponse
    {
        Gate::authorize('reports.viewChat');

        $dto = ReportsFilterDTO::fromRequest($request, $this->tenantId($request));
        $data = $this->agentPerformanceAction->execute($dto);

        return $this->success(
            new ReportResource($data, $dto->startDate->toDateString(), $dto->endDate->toDateString()),
            'Relatório de performance de agentes'
        );
    }

    /**
     * Relatório de CSAT / NPS.
     */
    public function csatNps(ReportsFilterRequest $request): JsonResponse
    {
        Gate::authorize('reports.viewChat');

        $dto = ReportsFilterDTO::fromRequest($request, $this->tenantId($request));
        $data = $this->csatNpsAction->execute($dto);

        return $this->success(
            new ReportResource($data, $dto->startDate->toDateString(), $dto->endDate->toDateString()),
            'Relatório de CSAT/NPS'
        );
    }

    /**
     * Relatório de Volume de Atendimento.
     */
    public function chatVolume(ReportsFilterRequest $request): JsonResponse
    {
        Gate::authorize('reports.viewChat');

        $dto = ReportsFilterDTO::fromRequest($request, $this->tenantId($request));
        $data = $this->chatVolumeAction->execute($dto);

        return $this->success(
            new ReportResource($data, $dto->startDate->toDateString(), $dto->endDate->toDateString()),
            'Relatório de volume de atendimento'
        );
    }

    /**
     * Relatório de Uso e Custo de IA.
     */
    public function aiUsageCost(ReportsFilterRequest $request): JsonResponse
    {
        Gate::authorize('reports.viewAi');

        $dto = ReportsFilterDTO::fromRequest($request, $this->tenantId($request));
        $data = $this->aiUsageCostAction->execute($dto);

        return $this->success(
            new ReportResource($data, $dto->startDate->toDateString(), $dto->endDate->toDateString()),
            'Relatório de uso e custo de IA'
        );
    }

    /**
     * Relatório de Faturamento.
     */
    public function billing(ReportsFilterRequest $request): JsonResponse
    {
        Gate::authorize('reports.viewBilling');

        $dto = ReportsFilterDTO::fromRequest($request, $this->tenantId($request));
        $data = $this->billingAction->execute($dto);

        return $this->success(
            new ReportResource($data, $dto->startDate->toDateString(), $dto->endDate->toDateString()),
            'Relatório de faturamento'
        );
    }

    /**
     * Relatório de Performance de Produtos.
     */
    public function productPerformance(ReportsFilterRequest $request): JsonResponse
    {
        Gate::authorize('reports.viewCrm');

        $dto = ReportsFilterDTO::fromRequest($request, $this->tenantId($request));
        $data = $this->productPerformanceAction->execute($dto);

        return $this->success(
            new ReportResource($data, $dto->startDate->toDateString(), $dto->endDate->toDateString()),
            'Relatório de performance de produtos'
        );
    }

    /**
     * Relatório de Performance de Automações.
     */
    public function autopilotPerformance(ReportsFilterRequest $request): JsonResponse
    {
        Gate::authorize('reports.viewAi');

        $dto = ReportsFilterDTO::fromRequest($request, $this->tenantId($request));
        $data = $this->autopilotAction->execute($dto);

        return $this->success(
            new ReportResource($data, $dto->startDate->toDateString(), $dto->endDate->toDateString()),
            'Relatório de performance de automações'
        );
    }

    /**
     * Relatório de Atividade da Equipe.
     */
    public function teamActivity(ReportsFilterRequest $request): JsonResponse
    {
        Gate::authorize('reports.viewAdmin');

        $dto = ReportsFilterDTO::fromRequest($request, $this->tenantId($request));
        $data = $this->teamActivityAction->execute($dto);

        return $this->success(
            new ReportResource($data, $dto->startDate->toDateString(), $dto->endDate->toDateString()),
            'Relatório de atividade da equipe'
        );
    }

    /**
     * Relatório de Contatos CRM.
     */
    public function contactCrm(ReportsFilterRequest $request): JsonResponse
    {
        Gate::authorize('reports.viewCrm');

        $dto = ReportsFilterDTO::fromRequest($request, $this->tenantId($request));
        $data = $this->contactCrmAction->execute($dto);

        return $this->success(
            new ReportResource($data, $dto->startDate->toDateString(), $dto->endDate->toDateString()),
            'Relatório de contatos CRM'
        );
    }

    /**
     * Exporta um relatório para CSV ou XLSX via download direto ou job assíncrono.
     *
     * @param  ReportsExportRequest  $request  Requisição com filtros e formato de exportação
     * @param  string  $report  Tipo do relatório (ex: sales-funnel, billing)
     * @return JsonResponse|StreamedResponse|BinaryFileResponse Resposta com o arquivo gerado
     */
    public function export(ReportsExportRequest $request, string $report): JsonResponse|StreamedResponse|BinaryFileResponse
    {
        Gate::authorize('reports.export');

        $dto = ReportsFilterDTO::fromRequest($request, $this->tenantId($request));
        $format = $request->validated('format') ?? 'csv';

        $action = $this->registry->resolve($report);

        if ($action === null) {
            return $this->error('Relatório não encontrado.', 404);
        }

        $data = $action->execute($dto);

        $filename = sprintf('%s_%s.%s', $report, now()->format('Y-m-d_His'), $format);

        if ($format === 'csv') {
            $exporter = new ReportCsvExporter;
            $content = $exporter->export($data, $report);

            return response()->streamDownload(function () use ($content): void {
                echo $content;
            }, $filename, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ]);
        }

        $exporter = new ReportXlsxExporter;
        $exporter->prepare($data, $report);

        /** @var BinaryFileResponse */
        return Excel::download($exporter, $filename);
    }
}
