<?php

declare(strict_types=1);

namespace Tests\E2E\Autopilot;

use Domain\Ai\Actions\AiAutopilotRunActions;
use Domain\Ai\Contracts\AIServiceInterface;
use Domain\Ai\Jobs\AiRunExecutionJob;
use Domain\Ai\Models\AiAgent;
use Domain\Ai\Models\AiAutopilotRun;
use Domain\Ai\Services\AiAgentToolPermissionService;
use Domain\Ai\Services\AiPermissionMatrixService;
use Domain\Ai\Services\AiPromptResolverService;
use Domain\Ai\Services\GuardrailEvaluatorService;
use Domain\Ai\Services\ToolDispatcherService;
use Domain\Gateway\DTOs\AI\AICompletionResponse;
use Domain\Gateway\Enums\AIFinishReason;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

final class ToolExceptionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_tool_failure_is_recorded_and_run_finishes_with_trace(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $agent = AiAgent::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'is_active' => true,
        ]);

        $run = AiAutopilotRun::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'status' => 'queued',
            'completed_at' => null,
            'input_context' => [
                'agent_id' => (string) $agent->id,
                'ticket_id' => (string) Str::orderedUuid(),
                'current_input' => 'preciso de proposta',
            ],
        ]);

        $redisMock = Mockery::mock();
        $redisMock->shouldReceive('publish')->atLeast()->once()->andReturn(1);
        Redis::shouldReceive('connection')->atLeast()->once()->andReturn($redisMock);

        $aiService = Mockery::mock(AIServiceInterface::class);
        $aiService->shouldReceive('complete')
            ->once()
            ->andReturn(new AICompletionResponse(
                content: 'tool call',
                promptTokens: 30,
                completionTokens: 10,
                totalTokens: 40,
                model: 'gpt-4o-mini',
                finishReason: AIFinishReason::TOOL_CALLS,
                toolCalls: [[
                    'name' => 'send_message',
                    'arguments' => ['content' => 'retornando agora'],
                ]]
            ));
        $aiService->shouldReceive('complete')
            ->once()
            ->andReturn(new AICompletionResponse(
                content: 'resposta final após falha da tool',
                promptTokens: 20,
                completionTokens: 8,
                totalTokens: 28,
                model: 'gpt-4o-mini',
                finishReason: AIFinishReason::STOP,
                toolCalls: []
            ));
        $aiService->shouldReceive('getProvider')->andReturn('openai');

        $actions = new AiAutopilotRunActions(
            aiService: $aiService,
            promptResolver: new AiPromptResolverService,
            toolDispatcher: new ToolDispatcherService(
                agentToolPermissionService: new AiAgentToolPermissionService,
                permissionMatrixService: new AiPermissionMatrixService
            ),
            guardrailEvaluator: new GuardrailEvaluatorService
        );

        (new AiRunExecutionJob((string) $run->id))->handle($actions);

        $run->refresh();
        $this->assertSame('completed', (string) $run->status);

        $toolTrace = (array) data_get($run->output, 'tool_trace', []);
        $this->assertNotEmpty($toolTrace);
        $this->assertFalse((bool) data_get($toolTrace, '0.success'));
        $this->assertSame('agent_tools_not_configured', data_get($toolTrace, '0.result.data.reason'));
    }
}
