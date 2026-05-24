<?php

declare(strict_types=1);

namespace Domain\Billing\Http\Controllers;

use Domain\Billing\Http\Requests\BillingUsageCheckRequest;
use Domain\Billing\Jobs\CheckUsageThresholdsJob;
use Domain\Billing\Models\AiMessageUsageFailedLog;
use Domain\Billing\Services\UsageCounterService;
use Domain\Shared\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller for AI message usage billing endpoints.
 *
 * Provides check-and-increment (gated AI usage) and fail-open logging
 * for cases where usage tracking fails but the AI response proceeds.
 */
final class BillingUsageController extends BaseController
{
    public function __construct(
        private readonly UsageCounterService $usageCounter,
    ) {}

    /**
     * Check if a message is allowed and increment the usage counter.
     *
     * Returns the usage result: allowed status, current count, limit, mode, overage flag.
     * Dispatches threshold check job asynchronously.
     */
    public function check(BillingUsageCheckRequest $request): JsonResponse
    {
        $tenantId = (string) $request->validated('tenant_id');
        $channel = (string) $request->validated('channel');
        $aiTurnId = (string) $request->validated('ai_turn_id');

        $result = $this->usageCounter->checkAndIncrement($tenantId, $channel, $aiTurnId);

        CheckUsageThresholdsJob::dispatch($tenantId, $result->cycleStart);

        return $this->success($result->toArray(), 'Usage checked');
    }

    /**
     * Log a failed-open usage attempt for later reconciliation.
     *
     * Called when the AI gateway cannot reach this endpoint but still
     * proceeds with the response (fail-open). The failed log is later
     * reconciled by ReconcileFailedUsageJob.
     */
    public function logFailure(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tenant_id' => ['required', 'uuid'],
            'ai_turn_id' => ['required', 'uuid'],
            'channel' => ['required', 'string', 'max:20'],
            'attempted_at' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $log = AiMessageUsageFailedLog::query()->firstOrCreate(
            ['ai_turn_id' => $data['ai_turn_id']],
            [
                'tenant_id' => $data['tenant_id'],
                'channel' => $data['channel'],
                'attempted_at' => $data['attempted_at'],
                'reason' => $data['reason'] ?? null,
                'reconciled_at' => null,
            ]
        );

        return $this->created($log->only([
            'id', 'tenant_id', 'ai_turn_id', 'channel', 'attempted_at', 'reason',
        ]), 'Failure logged');
    }
}
