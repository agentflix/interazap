<?php

declare(strict_types=1);

namespace Domain\Reports;

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
use Domain\Reports\Contracts\ReportActionInterface;
use InvalidArgumentException;

/**
 * Registro centralizado de report_type → action class.
 */
final class ReportActionRegistry
{
    /** @var array<string, class-string> */
    private const MAP = [
        'sales-funnel' => GetSalesFunnelReportAction::class,
        'revenue-sales' => GetRevenueSalesReportAction::class,
        'salesperson-performance' => GetSalespersonPerformanceReportAction::class,
        'loss-reasons' => GetLossReasonReportAction::class,
        'sla-resolution' => GetSlaResolutionReportAction::class,
        'agent-performance' => GetAgentPerformanceReportAction::class,
        'csat-nps' => GetCsatNpsReportAction::class,
        'chat-volume' => GetChatVolumeReportAction::class,
        'ai-usage-cost' => GetAiUsageCostReportAction::class,
        'billing' => GetBillingReportAction::class,
        'product-performance' => GetProductPerformanceReportAction::class,
        'autopilot-performance' => GetAutopilotPerformanceReportAction::class,
        'team-activity' => GetTeamActivityReportAction::class,
        'contact-crm' => GetContactCrmReportAction::class,
    ];

    /**
     * Resolve a action para o tipo de relatório.
     */
    public function resolve(string $type): ?ReportActionInterface
    {
        $class = self::MAP[$type] ?? null;

        if ($class === null) {
            return null;
        }

        if (! is_subclass_of($class, ReportActionInterface::class)) {
            throw new InvalidArgumentException(sprintf(
                'Report action "%s" must implement %s.',
                $class,
                ReportActionInterface::class,
            ));
        }

        /** @var ReportActionInterface $action */
        $action = app($class);

        return $action;
    }
}
