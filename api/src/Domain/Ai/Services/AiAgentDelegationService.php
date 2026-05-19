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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Handles parent → child run delegation rules.
 */
final class AiAgentDelegationService
{
    public function __construct(
        private readonly AutopilotRunStreamPublisher $streamPublisher,
        private readonly AutopilotRunSnapshotResolver $snapshotResolver,
    ) {}

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
            Log::warning('[AiAgentDelegation] No delegation rule found', [
                'tenant_id' => $tenantId,
                'source_agent_id' => $sourceAgentId,
                'target_agent_id' => $targetAgentId,
            ]);

            return [
                'success' => false,
                'message' => "No active delegation rule from agent {$sourceAgentId} to agent {$targetAgentId}.",
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
            $this->publishChildRunToStream($childRun, $targetAgent, $payload);
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
                ->first(['id', 'parent_run_id', 'tenant_id', 'input_context']);

            if ($parentOfCurrent === null) {
                break;
            }

            // O agent_id vive em input_context['agent_id'] — a tabela
            // ai_autopilot_runs não tem coluna dedicada para isso.
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

    /**
     * Publica o child run no stream `ai.run.request` para que o gateway o
     * processe pelo mesmo caminho rápido do dispatch inicial (com snapshot,
     * tools paralelas e sliding window). Substitui o antigo
     * AiRunExecutionJob (caminho síncrono PHP) que pagava 2–3 round-trips
     * HTTP por run e não tinha paralelização de tools.
     *
     * @param  array<string, mixed>  $payload
     */
    private function publishChildRunToStream(AiAutopilotRun $childRun, AiAgent $targetAgent, array $payload): void
    {
        $tenantId = (string) $childRun->tenant_id;
        $inputContext = is_array($childRun->input_context) ? $childRun->input_context : [];
        $ticketId = (string) ($inputContext['ticket_id'] ?? '');
        $messageId = (string) ($inputContext['message_id'] ?? '');
        $instanceId = (string) ($inputContext['instance_id'] ?? '');
        $contactId = (string) ($inputContext['contact_id'] ?? '');
        $sourceId = (string) ($inputContext['source_id'] ?? $ticketId);
        $inputText = (string) ($payload['input_text'] ?? $payload['question'] ?? $payload['message'] ?? ($inputContext['body'] ?? ''));

        $modelId = is_string($targetAgent->model_id) && $targetAgent->model_id !== ''
            ? $targetAgent->model_id
            : null;

        $delegationStack = is_array($inputContext['delegation_stack'] ?? null)
            ? array_values(array_filter($inputContext['delegation_stack'], 'is_string'))
            : [];
        $delegationStack[] = (string) ($inputContext['delegated_from_agent_id'] ?? '');
        $delegationStack = array_values(array_filter($delegationStack, fn ($v) => $v !== ''));

        $streamPayload = [
            'event' => 'ai.run.request',
            'run_id' => (string) $childRun->id,
            'tenant_id' => $tenantId,
            'agent_id' => (string) $targetAgent->id,
            // agent_role é APENAS observabilidade/telemetria — NÃO é usado para
            // autorização. As tools disponíveis são resolvidas pelo
            // AutopilotRunSnapshotResolver via ai_agent_tools (banco).
            'agent_role' => (string) ($targetAgent->getAttribute('role') ?? ''),
            'parent_run_id' => (string) ($childRun->parent_run_id ?? ''),
            'delegation_depth' => (int) ($childRun->delegation_depth ?? 1),
            'delegation_stack' => $delegationStack,
            'ticket_id' => $ticketId,
            'contact_id' => $contactId,
            'input_text' => $inputText,
            'source' => 'agent_delegation',
            'trigger_type' => 'delegation',
            'source_id' => $sourceId,
            'instance_id' => $instanceId,
            'playbook_id' => (string) ($childRun->playbook_id ?? ''),
            'streaming_enabled' => true,
            'max_tokens' => (int) config('ai.autopilot.max_tokens', 800),
            'max_tool_iterations' => (int) config('ai.autopilot.max_tool_iterations', 5),
            'run_token_budget' => (int) config('ai.autopilot.run_token_budget', 3000),
            'compact_tool_results' => (bool) config('ai.autopilot.compact_tool_results', true) ? 'true' : 'false',
            'requested_at' => now()->toIso8601String(),
        ];

        if ($modelId !== null) {
            $streamPayload['model'] = $modelId;
        }

        $snapshot = $this->snapshotResolver->resolve($tenantId, $targetAgent, $ticketId);
        $streamPayload['hydrated_at'] = $snapshot['hydrated_at'];

        if (is_string($snapshot['prompt']) && $snapshot['prompt'] !== '') {
            $streamPayload['prompt'] = $snapshot['prompt'];
        }

        if (is_array($snapshot['context'])) {
            $streamPayload['context'] = $snapshot['context'];
        }

        if (is_array($snapshot['tools']) && $snapshot['tools'] !== []) {
            $streamPayload['tools'] = $snapshot['tools'];
        }

        try {
            $this->streamPublisher->publish($streamPayload);

            Log::info('[AiAgentDelegation] Published child run to ai.run.request', [
                'tenant_id' => $tenantId,
                'run_id' => (string) $childRun->id,
                'parent_run_id' => (string) ($childRun->parent_run_id ?? ''),
                'target_agent_id' => (string) $targetAgent->id,
                'delegation_depth' => (int) ($childRun->delegation_depth ?? 1),
            ]);
        } catch (\Throwable $exception) {
            Log::error('[AiAgentDelegation] Failed to publish child run, falling back to PHP job', [
                'tenant_id' => $tenantId,
                'run_id' => (string) $childRun->id,
                'error' => $exception->getMessage(),
            ]);

            // Fallback: caminho lento PHP (mantém confiabilidade se o stream falhar).
            AiRunExecutionJob::dispatch((string) $childRun->id)
                ->onConnection(config('ai.autopilot.queue_connection', 'redis'))
                ->onQueue(config('ai.autopilot.queue_name', 'ai'));
        }
    }
}
