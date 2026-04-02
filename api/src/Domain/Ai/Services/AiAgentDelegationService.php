<?php

declare(strict_types=1);

namespace Domain\Ai\Services;

use Domain\Ai\Events\AiRunDelegated;
use Domain\Ai\Events\AiRunDelegating;
use Domain\Ai\Jobs\AiRunExecutionJob;
use Domain\Ai\Models\AiAgent;
use Domain\Ai\Models\AiAgentDelegation;
use Domain\Ai\Models\AiAutopilotRun;
use Domain\Ai\Models\AiUsageLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Handles parent → child run delegation rules.
 */
final class AiAgentDelegationService
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{success: bool, message: string, child_run_id?: string, return_after?: bool}
     */
    public function delegate(
        string $tenantId,
        string $parentRunId,
        string $sourceAgentId,
        string $targetAgentId,
        array $payload = [],
        bool $dispatchExecutionJob = true,
    ): array {
        $parentRun = AiAutopilotRun::query()
            ->where('tenant_id', $tenantId)
            ->find($parentRunId);

        if (! $parentRun) {
            return [
                'success' => false,
                'message' => 'Parent run not found.',
            ];
        }

        // H-01: Validate source agent belongs to the tenant
        $sourceAgent = AiAgent::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $sourceAgentId)
            ->first();

        if (! $sourceAgent) {
            return [
                'success' => false,
                'message' => 'Source agent does not belong to tenant.',
            ];
        }

        $targetAgentQuery = AiAgent::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true);

        $targetAgent = Str::isUuid($targetAgentId)
            ? $targetAgentQuery->find($targetAgentId)
            : $targetAgentQuery->whereRaw('LOWER(name) = ?', [mb_strtolower($targetAgentId)])->first();

        if (! $targetAgent) {
            return [
                'success' => false,
                'message' => 'Target agent not found or inactive.',
            ];
        }

        $targetAgentId = (string) $targetAgent->id;

        $delegationRule = AiAgentDelegation::query()
            ->where('tenant_id', $tenantId)
            ->where('source_agent_id', $sourceAgentId)
            ->where('target_agent_id', $targetAgentId)
            ->where('is_active', true)
            ->first();

        if (! $delegationRule) {
            return [
                'success' => false,
                'message' => 'Delegation rule not allowed for this source/target pair.',
            ];
        }

        $currentDepth = (int) ($parentRun->delegation_depth ?? 0);
        $maxDepth = max(1, (int) ($delegationRule->max_depth ?? 1));

        if ($currentDepth >= $maxDepth) {
            return [
                'success' => false,
                'message' => 'Delegation max depth reached.',
            ];
        }

        // C-05: Atomic lock to serialize concurrent delegations from the same parent run
        $lockKey = "ai:delegation:lock:{$parentRun->id}";
        $lock = Cache::lock($lockKey, 10);

        if (! $lock->get()) {
            return [
                'success' => false,
                'message' => 'Concurrent delegation in progress for this run.',
            ];
        }

        try {
            // C-05: Tenant-scoped circular delegation check walking the parent chain
            if ($this->createsCircularDelegation($tenantId, $parentRun, $targetAgentId)) {
                return [
                    'success' => false,
                    'message' => 'Circular delegation detected.',
                ];
            }

            AiRunDelegating::dispatch(
                $tenantId,
                (string) $parentRun->id,
                $sourceAgentId,
                $targetAgentId,
                $payload,
            );

            // H-05: Idempotency — prevent duplicate child runs on retry
            $existingChild = AiAutopilotRun::query()
                ->where('parent_run_id', (string) $parentRun->id)
                ->where('input_context->agent_id', $targetAgentId)
                ->whereIn('status', ['pending', 'queued', 'running'])
                ->first();

            if ($existingChild) {
                return [
                    'success' => true,
                    'message' => 'Delegation already exists (idempotent).',
                    'child_run_id' => (string) $existingChild->id,
                    'return_after' => (bool) ($payload['return_after'] ?? true),
                ];
            }

            $childPlaybookId = $payload['target_playbook_id'] ?? $parentRun->playbook_id;
            $childPlaybookId = is_string($childPlaybookId) ? trim($childPlaybookId) : $childPlaybookId;
            if ($childPlaybookId === '') {
                $childPlaybookId = null;
            }
            $returnAfter = (bool) ($payload['return_after'] ?? true);

            $childRun = AiAutopilotRun::query()->create([
                'id' => (string) Str::orderedUuid(),
                'tenant_id' => $tenantId,
                'playbook_id' => $childPlaybookId,
                'playbook_version' => (int) $parentRun->playbook_version,
                'status' => 'queued',
                'parent_run_id' => (string) $parentRun->id,
                'delegation_depth' => $currentDepth + 1,
                'input_context' => array_merge(
                    is_array($parentRun->input_context) ? $parentRun->input_context : [],
                    [
                        'agent_id' => $targetAgentId,
                        'delegated_from_agent_id' => $sourceAgentId,
                        'delegated_from_run_id' => (string) $parentRun->id,
                        'delegation_payload' => $payload,
                    ],
                ),
            ]);
        } finally {
            $lock->release();
        }

        if ($dispatchExecutionJob) {
            AiRunExecutionJob::dispatch((string) $childRun->id)
                ->onConnection(config('ai.autopilot.queue_connection', 'redis'))
                ->onQueue(config('ai.autopilot.queue_name', 'ai'));
        }

        AiRunDelegated::dispatch(
            $tenantId,
            (string) $parentRun->id,
            (string) $childRun->id,
            $sourceAgentId,
            $targetAgentId,
            $payload,
        );

        AiUsageLog::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenantId,
            'model_name' => 'delegation',
            'provider' => 'internal',
            'input_tokens' => 0,
            'output_tokens' => 0,
            'cached_prompt_tokens' => 0,
            'feature' => 'autopilot.delegation',
            'usable_type' => AiAutopilotRun::class,
            'usable_id' => (string) $parentRun->id,
            'metadata' => [
                'parent_run_id' => (string) $parentRun->id,
                'child_run_id' => (string) $childRun->id,
                'source_agent_id' => $sourceAgentId,
                'target_agent_id' => $targetAgentId,
                'delegation_depth' => (int) $childRun->delegation_depth,
                'return_after' => $returnAfter,
                'shared_budget_scope' => 'parent_child',
            ],
        ]);

        return [
            'success' => true,
            'message' => 'Delegation enqueued.',
            'child_run_id' => (string) $childRun->id,
            'return_after' => $returnAfter,
        ];
    }

    /**
     * Detect circular delegation by walking the parent chain with tenant isolation.
     *
     * Uses direct queries (not eager-loaded relation) to ensure fresh data
     * inside the lock window, preventing TOCTOU race conditions.
     */
    private function createsCircularDelegation(string $tenantId, AiAutopilotRun $parentRun, string $targetAgentId): bool
    {
        $currentRun = $parentRun;
        $maxChainDepth = 5;
        $depth = 0;

        while ($currentRun->parent_run_id !== null && $depth < $maxChainDepth) {
            $depth++;

            /** @var AiAutopilotRun|null $parentOfCurrent */
            $parentOfCurrent = AiAutopilotRun::query()
                ->where('tenant_id', $tenantId)
                ->where('id', $currentRun->parent_run_id)
                ->first(['id', 'agent_id', 'parent_run_id', 'tenant_id', 'input_context']);

            if ($parentOfCurrent === null) {
                break;
            }

            // Check agent_id column directly
            if ((string) $parentOfCurrent->agent_id === $targetAgentId) {
                return true;
            }

            // Also check input_context for backwards compatibility
            $inputContext = is_array($parentOfCurrent->input_context) ? $parentOfCurrent->input_context : [];
            $contextAgentId = (string) ($inputContext['agent_id'] ?? '');

            if ($contextAgentId !== '' && $contextAgentId === $targetAgentId) {
                return true;
            }

            $currentRun = $parentOfCurrent;
        }

        // Also check the parentRun itself (the immediate parent)
        $parentInputContext = is_array($parentRun->input_context) ? $parentRun->input_context : [];
        $parentContextAgentId = (string) ($parentInputContext['agent_id'] ?? '');

        if ($parentContextAgentId !== '' && $parentContextAgentId === $targetAgentId) {
            return true;
        }

        return false;
    }
}
