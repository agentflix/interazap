<?php

declare(strict_types=1);

use Domain\Ai\Actions\AiAutopilotRunActions;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

describe('AiAutopilotRunActions token aggregation', function (): void {
    it('aggregates tokens across tool iterations', function (): void {
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
            ],
        ]);

        $aiService = mock(AIServiceInterface::class);
        $aiService->shouldReceive('complete')->andReturn(
            new AICompletionResponse(
                content: json_encode([
                    ['name' => AiToolEnum::UPDATE_LEAD_SCORE, 'arguments' => []],
                ], JSON_THROW_ON_ERROR),
                promptTokens: 10,
                completionTokens: 5,
                totalTokens: 15,
                model: 'gpt-4o',
                finishReason: AIFinishReason::TOOL_CALLS,
                toolCalls: [
                    ['name' => AiToolEnum::UPDATE_LEAD_SCORE, 'arguments' => []],
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

        $toolDispatcher = new ToolDispatcherService(new AiPermissionMatrixService);

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

        $run = AiAutopilotRun::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenant->id,
            'playbook_id' => $playbook->id,
            'playbook_version' => 2,
            'status' => 'queued',
            'input_context' => [
                'agent_role' => AiAgentRole::SUPPORT_L1->value,
            ],
        ]);

        $aiService = mock(AIServiceInterface::class);
        $aiService->shouldReceive('complete')->andThrow(new RuntimeException('boom'));

        $toolDispatcher = new ToolDispatcherService(new AiPermissionMatrixService);

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
