<?php

declare(strict_types=1);

use Domain\Ai\Actions\AiAutopilotRunActions;
use Domain\Ai\Contracts\AiAgentToolPermissionServiceInterface;
use Domain\Ai\Contracts\AIServiceInterface;
use Domain\Ai\Enums\AiAgentRole;
use Domain\Ai\Enums\AiToolEnum;
use Domain\Ai\Models\AiAutopilotPlaybook;
use Domain\Ai\Models\AiAutopilotRun;
use Domain\Ai\Services\AiPermissionMatrixService;
use Domain\Ai\Services\AiPromptResolverService;
use Domain\Ai\Services\AiSkillExecutorService;
use Domain\Ai\Services\GuardrailEvaluatorService;
use Domain\Ai\Services\ToolDispatcherService;
use Domain\Gateway\DTOs\AI\AICompletionResponse;
use Domain\Gateway\Enums\AIFinishReason;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;

uses(LazilyRefreshDatabase::class);

describe('AiAutopilotRunActions token aggregation', function (): void {
    it('aggregates tokens across tool iterations', function (): void {
        $tenant = PlatformTenant::factory()->create();
        $playbook = AiAutopilotPlaybook::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        $agentId = (string) Str::orderedUuid();
        $toolName = AiToolEnum::UPDATE_LEAD_SCORE;

        $run = AiAutopilotRun::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenant->id,
            'playbook_id' => $playbook->id,
            'playbook_version' => 2,
            'status' => 'queued',
            'input_context' => [
                'agent_id' => $agentId,
                'agent_role' => AiAgentRole::SUPPORT_L1->value,
            ],
        ]);

        $aiService = mock(AIServiceInterface::class);
        $aiService->shouldReceive('complete')->andReturn(
            new AICompletionResponse(
                content: json_encode([
                    ['name' => $toolName, 'arguments' => []],
                ], JSON_THROW_ON_ERROR),
                promptTokens: 10,
                completionTokens: 5,
                totalTokens: 15,
                model: 'gpt-4o',
                finishReason: AIFinishReason::TOOL_CALLS,
                toolCalls: [
                    ['name' => $toolName, 'arguments' => []],
                ],
            ),
            new AICompletionResponse(
                content: 'final',
                promptTokens: 7,
                completionTokens: 3,
                totalTokens: 10,
                model: 'gpt-4o',
                finishReason: AIFinishReason::STOP,
                toolCalls: [],
            ),
        );

        $agentToolPermissionService = mock(AiAgentToolPermissionServiceInterface::class);
        $agentToolPermissionService->shouldReceive('toolNamesForAgent')
            ->with($tenant->id, $agentId)
            ->andReturn([$toolName]);
        $agentToolPermissionService->shouldReceive('agentCanUseTool')
            ->with($tenant->id, $agentId, $toolName)
            ->andReturnTrue();

        $toolDispatcher = new ToolDispatcherService(
            agentToolPermissionService: $agentToolPermissionService,
            permissionMatrixService: new AiPermissionMatrixService,
        );

        $actions = new AiAutopilotRunActions(
            aiService: $aiService,
            promptResolver: new AiPromptResolverService,
            toolDispatcher: $toolDispatcher,
            guardrailEvaluator: new GuardrailEvaluatorService,
            skillExecutor: new AiSkillExecutorService,
        );

        $result = $actions->executeWithEvents($run);

        expect($result['output']['raw']['prompt_tokens'])->toBe(17);
        expect($result['output']['raw']['completion_tokens'])->toBe(8);
        expect($result['output']['raw']['total_tokens'])->toBe(25);
    });

    it('marks run as failed when completion throws', function (): void {
        $tenant = PlatformTenant::factory()->create();
        $playbook = AiAutopilotPlaybook::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        $agentId = (string) Str::orderedUuid();

        $run = AiAutopilotRun::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenant->id,
            'playbook_id' => $playbook->id,
            'playbook_version' => 2,
            'status' => 'queued',
            'input_context' => [
                'agent_id' => $agentId,
                'agent_role' => AiAgentRole::SUPPORT_L1->value,
            ],
        ]);

        $aiService = mock(AIServiceInterface::class);
        $aiService->shouldReceive('complete')->andThrow(new RuntimeException('boom'));

        $agentToolPermissionService = mock(AiAgentToolPermissionServiceInterface::class);
        $agentToolPermissionService->shouldReceive('toolNamesForAgent')
            ->with($tenant->id, $agentId)
            ->andReturn([]);

        $toolDispatcher = new ToolDispatcherService(
            agentToolPermissionService: $agentToolPermissionService,
            permissionMatrixService: new AiPermissionMatrixService,
        );

        $actions = new AiAutopilotRunActions(
            aiService: $aiService,
            promptResolver: new AiPromptResolverService,
            toolDispatcher: $toolDispatcher,
            guardrailEvaluator: new GuardrailEvaluatorService,
            skillExecutor: new AiSkillExecutorService,
        );

        try {
            $actions->executeWithEvents($run);
        } catch (RuntimeException $e) {
            expect($e->getMessage())->toBe('boom');
        }
    });
});

describe('AiAutopilotRunActions agent_id validation', function (): void {
    it('returns blocked state when agent_id is missing from context', function (): void {
        $tenant = PlatformTenant::factory()->create();
        $playbook = AiAutopilotPlaybook::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        $run = AiAutopilotRun::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenant->id,
            'playbook_id' => $playbook->id,
            'playbook_version' => 2,
            'status' => 'queued',
            'input_context' => [
                'agent_role' => AiAgentRole::SUPPORT_L1->value,
                // agent_id intentionally missing
            ],
        ]);

        $aiService = mock(AIServiceInterface::class);
        $aiService->shouldNotReceive('complete');

        $agentToolPermissionService = mock(AiAgentToolPermissionServiceInterface::class);
        $agentToolPermissionService->shouldNotReceive('toolNamesForAgent');

        $toolDispatcher = new ToolDispatcherService(
            agentToolPermissionService: $agentToolPermissionService,
            permissionMatrixService: new AiPermissionMatrixService,
        );

        $actions = new AiAutopilotRunActions(
            aiService: $aiService,
            promptResolver: new AiPromptResolverService,
            toolDispatcher: $toolDispatcher,
            guardrailEvaluator: new GuardrailEvaluatorService,
            skillExecutor: new AiSkillExecutorService,
        );

        $result = $actions->executeWithEvents($run);

        expect($result['output']['blocked'])->toBeTrue();
        expect($result['output']['error'])->toBe('Agent context not informed.');
        expect($result['output']['hasMoreIterations'])->toBeFalse();
        expect($result['hasMore'])->toBeFalse();
        expect($result['messages'])->toBe([]);

        // Verify run was persisted as blocked
        $run->refresh();
        expect($run->status)->toBe('blocked');
        expect($run->output['blocked'])->toBeTrue();
    });

    it('does not expose tools not linked to the agent', function (): void {
        $tenant = PlatformTenant::factory()->create();
        $playbook = AiAutopilotPlaybook::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        $agentId = (string) Str::orderedUuid();

        $run = AiAutopilotRun::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenant->id,
            'playbook_id' => $playbook->id,
            'playbook_version' => 2,
            'status' => 'queued',
            'input_context' => [
                'agent_id' => $agentId,
                'agent_role' => AiAgentRole::SUPPORT_L1->value,
            ],
        ]);

        // Agent only has SEND_MESSAGE tool, not UPDATE_LEAD_SCORE
        $agentToolPermissionService = mock(AiAgentToolPermissionServiceInterface::class);
        $agentToolPermissionService->shouldReceive('toolNamesForAgent')
            ->with($tenant->id, $agentId)
            ->andReturn(['send_message']);

        $toolDispatcher = new ToolDispatcherService(
            agentToolPermissionService: $agentToolPermissionService,
            permissionMatrixService: new AiPermissionMatrixService,
        );

        $aiService = mock(AIServiceInterface::class);
        // LLM returns no tool calls — just a final response
        $aiService->shouldReceive('complete')->andReturn(
            new AICompletionResponse(
                content: 'Done',
                promptTokens: 5,
                completionTokens: 3,
                totalTokens: 8,
                model: 'gpt-4o',
                finishReason: AIFinishReason::STOP,
                toolCalls: [],
            ),
        );

        $actions = new AiAutopilotRunActions(
            aiService: $aiService,
            promptResolver: new AiPromptResolverService,
            toolDispatcher: $toolDispatcher,
            guardrailEvaluator: new GuardrailEvaluatorService,
            skillExecutor: new AiSkillExecutorService,
        );

        $result = $actions->executeWithEvents($run);

        // Tool definitions should only contain send_message, not update_lead_score
        expect($result['output']['blocked'])->toBeFalse();
        expect($result['output']['response'])->toBe('Done');
    });

    it('getToolDefinitions is called with agent_id not agent_role', function (): void {
        $tenant = PlatformTenant::factory()->create();
        $playbook = AiAutopilotPlaybook::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        $agentId = (string) Str::orderedUuid();
        $toolName = AiToolEnum::SEND_MESSAGE;

        $run = AiAutopilotRun::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenant->id,
            'playbook_id' => $playbook->id,
            'playbook_version' => 2,
            'status' => 'queued',
            'input_context' => [
                'agent_id' => $agentId,
                'agent_role' => AiAgentRole::SALES_QUALIFIER->value,
                'messages' => [],
            ],
        ]);

        $capturedAgentId = null;

        $agentToolPermissionService = mock(AiAgentToolPermissionServiceInterface::class);
        $agentToolPermissionService->shouldReceive('toolNamesForAgent')
            ->andReturnUsing(function (string $tid, string $aid) use (&$capturedAgentId, $agentId, $toolName): array {
                $capturedAgentId = $aid;
                expect($aid)->toBe($agentId);

                return [$toolName];
            });

        $toolDispatcher = new ToolDispatcherService(
            agentToolPermissionService: $agentToolPermissionService,
            permissionMatrixService: new AiPermissionMatrixService,
        );

        $aiService = mock(AIServiceInterface::class);
        $aiService->shouldReceive('complete')->andReturn(
            new AICompletionResponse(
                content: 'Hello',
                promptTokens: 5,
                completionTokens: 3,
                totalTokens: 8,
                model: 'gpt-4o',
                finishReason: AIFinishReason::STOP,
                toolCalls: [],
            ),
        );

        $actions = new AiAutopilotRunActions(
            aiService: $aiService,
            promptResolver: new AiPromptResolverService,
            toolDispatcher: $toolDispatcher,
            guardrailEvaluator: new GuardrailEvaluatorService,
            skillExecutor: new AiSkillExecutorService,
        );

        $actions->executeWithEvents($run);

        // Verify that agent_id (not agent_role) was used to fetch tools
        expect($capturedAgentId)->toBe($agentId);
    });
});
