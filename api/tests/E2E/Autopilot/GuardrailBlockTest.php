<?php

declare(strict_types=1);

namespace Tests\E2E\Autopilot;

use Domain\Ai\Actions\AiAutopilotRunActions;
use Domain\Ai\Contracts\AIServiceInterface;
use Domain\Ai\Jobs\AiRunExecutionJob;
use Domain\Ai\Models\AiAgent;
use Domain\Ai\Models\AiAutopilotGuardrail;
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

final class GuardrailBlockTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_guardrail_block_aborts_run_and_records_reason(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $agent = AiAgent::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'is_active' => true,
        ]);

        AiAutopilotGuardrail::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'rule_type' => 'BLOCK',
            'is_active' => true,
            'conditions' => [
                'action_type' => 'tool_call',
                'tool' => 'send_message',
            ],
        ]);

        $run = AiAutopilotRun::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'status' => 'queued',
            'completed_at' => null,
            'input_context' => [
                'agent_id' => (string) $agent->id,
                'ticket_id' => (string) Str::orderedUuid(),
                'current_input' => 'envie mensagem',
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
                promptTokens: 15,
                completionTokens: 6,
                totalTokens: 21,
                model: 'gpt-4o-mini',
                finishReason: AIFinishReason::TOOL_CALLS,
                toolCalls: [[
                    'name' => 'send_message',
                    'arguments' => ['content' => 'teste'],
                ]]
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
        $this->assertSame('blocked', (string) $run->status);
        $this->assertSame('Execution blocked by guardrail policy.', (string) data_get($run->output, 'error'));
        $this->assertTrue((bool) data_get($run->output, 'blocked'));
    }
}
