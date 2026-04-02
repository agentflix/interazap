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
 * Controller for AI Usage analytics endpoints.
 *
 * Provides usage metrics, costs, and trends for tenant's AI consumption.
 */
final class AiUsageController extends BaseController
{
    public function __construct(
        private readonly AiUsageActions $actions,
        private readonly AiUsageSummaryAction $summaryAction,
        private readonly GetMediaTranscriptionReportAction $transcriptionReportAction,
    ) {}

    /**
     * Get usage summary for current period.
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
     * Get daily usage breakdown for last 30 days.
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
     * Get top AI agents by usage.
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
     * Get monthly usage history.
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
     * Get media transcription usage report.
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

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }
}
