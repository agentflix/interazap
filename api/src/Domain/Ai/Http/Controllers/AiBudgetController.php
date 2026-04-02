<?php

declare(strict_types=1);

namespace Domain\Ai\Http\Controllers;

use Domain\Ai\Http\Requests\AiBudgetUpdateRequest;
use Domain\Ai\Services\TokenBudgetService;
use Domain\Shared\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller for tenant token budget management.
 */
final class AiBudgetController extends BaseController
{
    public function __construct(private readonly TokenBudgetService $tokenBudgetService) {}

    /**
     * Show budget config and current usage for authenticated tenant.
     */
    public function show(Request $request): JsonResponse
    {
        $this->authorize('ai.autopilots.manage');

        $tenantId = $this->tenantId($request);

        return response()->json([
            'success' => true,
            'data' => [
                'config' => $this->tokenBudgetService->getBudgetConfig($tenantId),
                'usage' => $this->tokenBudgetService->getUsageStats($tenantId),
            ],
        ]);
    }

    /**
     * Update budget config for authenticated tenant.
     */
    public function update(AiBudgetUpdateRequest $request): JsonResponse
    {
        $this->authorize('ai.autopilots.manage');

        $tenantId = $this->tenantId($request);
        $current = $this->tokenBudgetService->getBudgetConfig($tenantId);

        $dailyLimit = $request->has('daily_limit')
            ? ($request->input('daily_limit') !== null ? (float) $request->input('daily_limit') : null)
            : $current['daily_limit'];

        $monthlyLimit = $request->has('monthly_limit')
            ? ($request->input('monthly_limit') !== null ? (float) $request->input('monthly_limit') : null)
            : $current['monthly_limit'];

        $this->tokenBudgetService->updateBudgetConfig($tenantId, $dailyLimit, $monthlyLimit);

        return response()->json([
            'success' => true,
            'message' => 'Budget configuration updated successfully.',
            'data' => [
                'config' => $this->tokenBudgetService->getBudgetConfig($tenantId),
                'usage' => $this->tokenBudgetService->getUsageStats($tenantId),
            ],
        ]);
    }
}
