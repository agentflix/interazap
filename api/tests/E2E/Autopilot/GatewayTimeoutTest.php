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
use Domain\Gateway\Exceptions\GatewayTimeoutException;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

final class GatewayTimeoutTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_gateway_timeout_marks_run_as_failed(): void
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
                'current_input' => 'cliente pediu ajuda',
            ],
        ]);

        $redisMock = Mockery::mock();
        $redisMock->shouldReceive('publish')->atLeast()->once()->andReturn(1);
        Redis::shouldReceive('connection')->atLeast()->once()->andReturn($redisMock);

        $aiService = Mockery::mock(AIServiceInterface::class);
        $aiService->shouldReceive('complete')
            ->once()
            ->andThrow(new GatewayTimeoutException('Gateway timeout em 30s', 'corr-timeout-1', 30));
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

        (new AiRunExecutionJob((string) $run->id, (string) $run->tenant_id))->handle($actions);

        $run->refresh();
        $this->assertSame('failed', (string) $run->status);
        $this->assertNotNull($run->completed_at);
        $this->assertStringContainsString('Gateway timeout', (string) data_get($run->output, 'error'));
    }
}
